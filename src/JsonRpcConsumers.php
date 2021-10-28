<?php

namespace Kiri\Rpc;


use Exception;
use Kiri\Consul\Catalog\Catalog;
use Kiri\Context;
use Kiri\Kiri;
use Kiri\Pool\Pool;
use Swoole\Client;
use Swoole\Coroutine;

/**
 *
 */
abstract class JsonRpcConsumers implements OnRpcConsumerInterface
{


	/**
	 * @var Pool
	 */
	public Pool $pool;


	/**
	 * @param string $service
	 * @param string $method
	 * @param mixed $data
	 * @param string $version
	 * @throws Exception
	 */
	public function notify(string $service, string $method, mixed $data, string $version = '2.0'): void
	{
		$config = $this->get_consul($service);
		if (Context::inCoroutine()) {
			$client = $this->clientOnCoroutine($config);
		} else {
			$client = $this->clientNotCoroutine($config);
		}
		$client->send(json_encode(['jsonrpc' => $version, 'method' => $method, 'params' => $data]));
		$client->close();
	}


	/**
	 * @param string $service
	 * @param string $method
	 * @param mixed $data
	 * @param string $version
	 * @param string $id
	 * @return mixed
	 * @throws Exception
	 */
	public function get(string $service, string $method, mixed $data, string $version = '2.0', string $id = ''): mixed
	{
		$config = $this->get_consul($service);
		if (Context::inCoroutine()) {
			$client = $this->clientOnCoroutine($config);
		} else {
			$client = $this->clientNotCoroutine($config);
		}
		$client->send(json_encode(['jsonrpc' => $version, 'method' => $method, 'params' => $data, 'id' => $id]));
		$read = $client->recv();
		$client->close();
		return json_decode($read, true);
	}


	/**
	 * @param string $service
	 * @param array $data
	 * @return mixed
	 * @throws Exception
	 */
	public function batch(string $service, array $data): mixed
	{
		$config = $this->get_consul($service);
		if (Context::inCoroutine()) {
			$client = $this->clientOnCoroutine($config);
		} else {
			$client = $this->clientNotCoroutine($config);
		}
		$client->send(json_encode($data, true));
		$read = $client->recv();
		$client->close();
		return json_decode($read, true);
	}


	/**
	 * @param $service
	 * @return array
	 */
	private function get_consul($service): array
	{
		$sf = Kiri::getDi()->get(Catalog::class);

		$content = $sf->service($service)->getBody()->getContents();

		$content = json_decode($content, true);

		return $content[array_rand($content)];
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
