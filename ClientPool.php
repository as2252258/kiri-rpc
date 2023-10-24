<?php

namespace Kiri\Rpc;

use Exception;
use Kiri;
use Kiri\Abstracts\Component;
use Kiri\Pool\Pool;
use Kiri\Server\Events\OnBeforeShutdown;
use ReflectionException;
use Swoole\Coroutine\Client as CoroutineClient;
use Swoole\Client as AsyncClient;


/**
 *
 */
class ClientPool extends Component
{

    public int $max;


    public int $min;


    private array $names = [];


    /**
     * @return void
     */
    public function init(): void
    {
        on(OnBeforeShutdown::class, [$this, 'onBeforeShutdown']);
    }


    /**
     * @return void
     * @throws Exception
     */
    public function onBeforeShutdown(): void
    {
        $pool = Kiri::getDi()->get(Pool::class);
        foreach ($this->names as $name) {
            $pool->clean($name);
        }
    }


    /**
     * @param $config
     * @return resource
     * @throws Exception
     */
    public function get($config): mixed
    {
        $coroutineName = $config['Address'] . '::' . $config['Port'];

        if (!in_array($coroutineName, $this->names)) {
            $this->names[] = $coroutineName;
        }

        return $this->getPool($config['Address'], $config['Port'])->get($coroutineName);
    }


    /**
     * @param CoroutineClient|AsyncClient $client
     * @param $host
     * @param $port
     * @throws Exception
     */
    public function push(CoroutineClient|AsyncClient $client, $host, $port)
    {
        $this->getPool($host, $port)->push($host . '::' . $port, $client);
    }


    /**
     * @param string $host
     * @param int $port
     * @return Pool
     * @throws ReflectionException
     */
    public function getPool(string $host, int $port): Pool
    {
        $pool = Kiri::getDi()->get(Pool::class);
        $pool->created($host . '::' . $port, 10, [$this, 'connect']);
        return $pool;
    }


    /**
     * @param $host
     * @param $port
     * @return CoroutineClient|AsyncClient
     * @throws Exception
     */
    public function connect($host, $port): CoroutineClient|AsyncClient
    {
        if (Kiri\Di\Context::inCoroutine()) {
            $client = new CoroutineClient(SWOOLE_SOCK_TCP);
        } else {
            $client = new AsyncClient(SWOOLE_SOCK_TCP);
        }
        if (!$client->connect($host, $port, 3)) {
            throw new Exception('Connect ' . $host . '::' . $port . ' fail');
        }
        return $client;
    }

}
