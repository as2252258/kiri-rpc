<?php

namespace Kiri\Rpc;


use Exception;
use Kiri\Context;
use Kiri\Core\Number;
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


	protected string $name = '';


	/**
	 * @param string $method
	 * @param mixed $data
	 * @param string $version
	 * @throws Exception
	 */
	public function notify(string $method, mixed $data, string $version = '2.0'): void
	{
		$config = $this->get_consul($this->name);
		if (Context::inCoroutine()) {
			$client = $this->clientOnCoroutine($config);
		} else {
			$client = $this->clientNotCoroutine($config);
		}
		$client->send(json_encode(['jsonrpc' => $version, 'service' => $this->name, 'method' => $method, 'params' => $data]));
		$client->recv(1);
		$client->close();
	}


	/**
	 * @param string $method
	 * @param mixed $data
	 * @param string $version
	 * @param string $id
	 * @return mixed
	 * @throws Exception
	 */
	public function get(string $method, mixed $data, string $version = '2.0', string $id = ''): mixed
	{
		$config = $this->get_consul($this->name);
		if (Context::inCoroutine()) {
			$client = $this->clientOnCoroutine($config);
		} else {
			$client = $this->clientNotCoroutine($config);
		}

		if (empty($id)) $id = Number::create(time());

		$client->send(json_encode(['jsonrpc' => $version, 'service' => $this->name, 'method' => $method, 'params' => $data, 'id' => $id]));
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
//		$sf = Kiri::getDi()->get(Catalog::class);
//
//		$content = $sf->service($service)->getBody()->getContents();
//
//		$content = json_decode($content, true);
//
//		return $content[array_rand($content)];

		return ['ServiceAddress' => '127.0.0.1', 'ServicePort' => 9526];
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
