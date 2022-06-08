<?php

namespace Kiri\Rpc;

use Exception;
use Kiri;
use Kiri\Abstracts\Component;
use Kiri\Annotation\Inject;
use Kiri\Events\EventProvider;
use Kiri\Exception\ConfigException;
use Kiri\Pool\Alias;
use Kiri\Pool\Pool;
use Kiri\Server\Events\OnBeforeShutdown;
use Swoole\Client;


/**
 *
 */
class ClientPool extends Component
{

	const POOL_NAME = 'rpc.client.pool';

	use Alias;


	public int $max;


	public int $min;


	public int $waite;


	#[Inject(EventProvider::class)]
	public EventProvider $provider;


	private array $names = [];


	public function init()
	{
		$this->provider->on(OnBeforeShutdown::class, [$this, 'onBeforeShutdown']);
	}


	/**
	 * @return void
	 * @throws Exception
	 */
	public function onBeforeShutdown()
	{
		foreach ($this->names as $name) {
			$this->getPool()->clean($name);
		}
	}


	/**
	 * @param $config
	 * @param callable $callback
	 * @return mixed
	 * @throws ConfigException
	 * @throws Exception
	 */
	public function get($config, callable $callback): mixed
	{
		$coroutineName = $this->name(self::POOL_NAME . '::' . $config['Address'] . '::' . $config['Port'], true);

		$pool = $config['pool'] ?? ['min' => 1, 'max' => 100];

		$this->names[] = $coroutineName;

		return $this->getPool()->get($coroutineName, $callback, $pool['min'] ?? 1);
	}


	/**
	 * @param \Swoole\Coroutine\Client|Client $client
	 * @param $host
	 * @param $port
	 * @throws ConfigException
	 * @throws Exception
	 */
	public function push(\Swoole\Coroutine\Client|Client $client, $host, $port)
	{
		$coroutineName = $this->name(self::POOL_NAME . '::' . $host . '::' . $port, true);

		$this->getPool()->push($coroutineName, $client);
	}


	/**
	 * @return Pool
	 * @throws Exception
	 */
	public function getPool(): Pool
	{
		return Kiri::getDi()->get(Pool::class);
	}

}
