<?php

namespace Kiri\Rpc;


use Exception;
use Http\Message\ServerRequest;
use Http\Message\Stream;
use Kiri\Core\Number;
use Kiri\Kiri;
use Kiri\Pool\Pool;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
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
	 * @throws ClientExceptionInterface
	 */
	public function notify(string $method, mixed $data, string $version = '2.0'): void
	{
		$config = $this->get_consul($this->name);
		$transporter = Kiri::getDi()->get(RpcClientInterface::class);
		$transporter->withConfig($config)->sendRequest(
			$this->requestBody([
				'jsonrpc' => $version,
				'service' => $this->name,
				'method'  => $method,
				'params'  => $data,
			])
		);
	}


	/**
	 * @param array $data
	 * @return ServerRequestInterface
	 */
	private function requestBody(array $data): ServerRequestInterface
	{
		$server = Kiri::getDi()->get(ServerRequest::class);
		return $server->withBody(new Stream(json_encode($data)));
	}


	/**
	 * @param string $method
	 * @param mixed $data
	 * @param string $version
	 * @param string $id
	 * @return mixed
	 * @throws Exception
	 * @throws ClientExceptionInterface
	 */
	public function get(string $method, mixed $data, string $version = '2.0', string $id = ''): ResponseInterface
	{
		if (empty($id)) $id = Number::create(time());

		$config = $this->get_consul($this->name);
		$transporter = Kiri::getDi()->get(RpcClientInterface::class);
		return $transporter->withConfig($config)->sendRequest(
			$this->requestBody([
				'jsonrpc' => $version,
				'service' => $this->name,
				'method'  => $method,
				'params'  => $data,
				'id'      => $id
			])
		);
	}


	/**
	 * @param array $data
	 * @return mixed
	 * @throws ClientExceptionInterface
	 * @throws Exception
	 */
	public function batch(array $data): mixed
	{
		$config = $this->get_consul($this->name);
		$transporter = Kiri::getDi()->get(RpcClientInterface::class);
		return $transporter->withConfig($config)->sendRequest(
			$this->requestBody($data)
		);
	}


	/**
	 * @param $service
	 * @return array
	 * @throws Exception
	 */
	private function get_consul($service): array
	{
		if (empty($service)) {
			throw new Exception('You need set rpc service name if used.');
		}
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
