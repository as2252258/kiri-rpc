<?php

namespace Kiri\Rpc;

use Exception;
use Kiri;
use Kiri\Abstracts\Component;
use Kiri\Events\EventProvider;
use Kiri\Exception\ConfigException;
use Kiri\Pool\Pool;
use Kiri\Di\Inject\Container;
use Kiri\Server\Events\OnBeforeShutdown;
use ReflectionException;
use Swoole\Coroutine\Client;


/**
 *
 */
class ClientPool extends Component
{

    public int $max;


    public int $min;


    private array $names = [];


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
     * @throws ConfigException
     * @throws ReflectionException
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
     * @param Client|\Swoole\Client $client
     * @param $host
     * @param $port
     * @throws ConfigException|ReflectionException
     */
    public function push(Client|\Swoole\Client $client, $host, $port)
    {
        $this->getPool($host, $port)->push($host . '::' . $port, $client);
    }


    /**
     * @param $host
     * @param $port
     * @return Pool
     * @throws ReflectionException
     */
    public function getPool($host, $port): Pool
    {
        $pool = Kiri::getDi()->get(Pool::class);
        $pool->created($host . '::' . $port, 10, function () use ($host, $port) {
            $client = stream_socket_client("tcp://$host:$port", $errCode, $errMessage, 3);
            if ($client === false) {
                throw new Exception('Connect ' . $host . '::' . $port . ' fail');
            }
            return $client;
        });
        return $pool;
    }

}
