<?php

namespace Kiri\Rpc;

use Exception;
use Kiri\Context;
use Swoole\Client;
use Swoole\Coroutine;

trait TraitTransporter
{


	protected array $config;


	protected array $clients = [];


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
	 * @param bool $isClose
	 * @return mixed
	 */
	private function request(Client|Coroutine\Client $client, $content, bool $isClose): mixed
	{
		$client->send($content);
		$read = $client->recv();
		if ($isClose) {
			$client->close();
		}
		return $read;
	}


	/**
	 * @return Client|Coroutine\Client
	 * @throws Exception
	 */
	private function newClient(): Coroutine\Client|Client
	{
		$alias = $this->alias($this->config);
		$client = $this->clients[$alias] ?? null;
		if (is_null($client)) {
			$client = Context::inCoroutine() ? new Coroutine\Client(SWOOLE_SOCK_TCP) : new Client(SWOOLE_SOCK_TCP);
			$this->clients[$alias] = $client;
		}
		[$host, $port] = [$this->config['Address'], $this->config['Port']];
		if (!$client->isConnected() && !$client->connect($host, $port, 60)) {
			throw new Exception('connect fail.');
		}
		return $client;
	}


	/**
	 * @param array $config
	 * @return string
	 */
	private function alias(array $config): string
	{
		return $config['Address'] . '::' . $config['Port'];
	}


}
