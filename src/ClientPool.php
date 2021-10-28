<?php

namespace Kiri\Rpc;

use Exception;
use Kiri\Abstracts\Component;
use Kiri\Context;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;
use Kiri\Pool\Alias;
use Kiri\Pool\Pool;
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


	/**
	 * @param $config
	 * @param callable $callback
	 * @return mixed
	 * @throws ConfigException
	 * @throws Exception
	 */
	public function get($config, callable $callback): mixed
	{
		$coroutineName = $this->name(self::POOL_NAME . '::' . $config['ServiceAddress'] . '::' . $config['ServicePort'], true);

		$pool = $config['pool'] ?? ['min' => 1, 'max' => 100];

		$clients = $this->getPool()->get($coroutineName, $callback(), $pool['min'] ?? 1);
		return Context::setContext($coroutineName, $clients);
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
