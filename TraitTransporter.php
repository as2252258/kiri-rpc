<?php

namespace Kiri\Rpc;

use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Kiri\Annotation\Inject;
use Kiri\Di\Context;
use Swoole\Client;
use Swoole\Coroutine;

trait TraitTransporter
{


	/**
	 * @var RpcManager
	 */
	#[Inject(RpcManager::class)]
	public RpcManager $manager;


	protected array $config;


	/**
	 * @param Client|Coroutine\Client $client
	 * @param $content
	 * @return string|bool
	 */
	private function request(Client|Coroutine\Client $client, $content): string|bool
	{
		$client->send($content);
		return $client->recv();
	}


	/**
	 * @param string $service
	 * @return $this
	 * @throws RpcServiceException
	 */
	private function get_consul(string $service): static
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
	private function _loadRand($services): array
	{
		$array = [];
		foreach ($services as $value) {
			$value['Weight'] = $value['Weights']['Passing'];
			$array[] = $value;
		}
		if (count($array) < 2) {
			$luck = $array[0];
		} else {
			$luck = Luckdraw::luck($array, 'Weight');
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
	private function newClient(): Coroutine\Client|Client
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
