<?php

namespace Kiri\Rpc;

use Exception;
use Kiri\Context;
use Swoole\Client;
use Swoole\Coroutine;

trait TraitTransporter
{


	protected array $config;


	/**
	 * @param $config
	 * @return $this
	 */
	public function withConfig($config): static
	{
		$this->config = $config;
		return $this;
	}


	/**
	 * @param Client|Coroutine\Client $client
	 * @param $content
	 * @return mixed
	 */
	private function request(Client|Coroutine\Client $client, $content): mixed
	{
		$client->send($content);
		$read = $client->recv();
		$client->close();
		return $read;
	}


	/**
	 * @return Client|Coroutine\Client
	 * @throws Exception
	 */
	private function newClient(): Coroutine\Client|Client
	{
		if (Context::inCoroutine()) {
			$client = $this->clientOnCoroutine($this->config);
		} else {
			$client = $this->clientNotCoroutine($this->config);
		}
		return $client;
	}


	/**
	 * @param $config
	 * @return Coroutine\Client
	 * @throws Exception
	 */
	private function clientOnCoroutine($config): Coroutine\Client
	{
		$client = new Coroutine\Client(SWOOLE_SOCK_TCP);
		if (!$client->connect($config['ServiceAddress'], $config['ServicePort'], 60)) {
			throw new Exception('connect fail.');
		}
		return $client;
	}


	/**
	 * @param $config
	 * @return Client
	 * @throws Exception
	 */
	private function clientNotCoroutine($config): Client
	{
		$client = new Client(SWOOLE_SOCK_TCP);
		if (!$client->connect($config['ServiceAddress'], $config['ServicePort'], 60)) {
			throw new Exception('connect fail.');
		}
		return $client;
	}


}
