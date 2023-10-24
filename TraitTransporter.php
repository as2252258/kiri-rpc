<?php

namespace Kiri\Rpc;

use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Kiri\Di\Context;
use Kiri\Di\Inject\Container;
use Swoole\Client;
use Swoole\Coroutine;

trait TraitTransporter
{


	/**
	 * @var RpcManager
	 */
	#[Container(RpcManager::class)]
	public RpcManager $manager;


	protected array $config;


	/**
	 * @param resource $client
	 * @param $content
	 * @return string|bool
	 */
    protected function request(mixed $client, $content): string|bool
	{
        socket_write($client, \msgpack_pack($content), mb_strlen($content));

        socket_read($client, 1024);

		return \msgpack_unpack($client->recv());
	}


	/**
	 * @param string $service
	 * @return $this
	 * @throws RpcServiceException
	 */
    protected function get_consul(string $service): static
	{
		if (empty($service)) {
			throw new RpcServiceException('You need set rpc service name if used.');
		}
		$sf = $this->manager->getServices($service);
		if (empty($sf) || !is_array($sf)) {
			throw new RpcServiceException('You need set rpc service name if used.');
		}
		$this->config = $this->_loadRand($sf);
		return $this;
	}


	/**
	 * @param $services
	 * @return array
	 */
	#[ArrayShape(['Address' => "mixed", 'Port' => "mixed"])]
    protected function _loadRand($services): array
	{
		$array = [];
		foreach ($services as $value) {
			$value['Weight'] = $value['Weights']['Passing'];
			$array[] = $value;
		}
		if (count($array) < 2) {
			$luck = $array[0];
		} else {
			$luck = LotteryDraw::luck($array, 'Weight');
		}
		return [
			'Address' => $luck['TaggedAddresses']['wan_ipv4']['Address'],
			'Port'    => $luck['TaggedAddresses']['wan_ipv4']['Port']
		];
	}



	/**
	 * @return Client|Coroutine\Client
	 * @throws Exception
	 */
	protected function newClient(): Coroutine\Client|Client
	{
		if (Context::inCoroutine()) {
			$client = new Coroutine\Client(SWOOLE_SOCK_TCP);
		} else {
            $client = new Client(SWOOLE_SOCK_TCP);
        }
		[$host, $port] = [$this->config['Address'], $this->config['Port']];
		if (!$client->isConnected() && !$client->connect($host, $port, 60)) {
			throw new Exception('connect fail.');
		}
		return $client;
	}

}
