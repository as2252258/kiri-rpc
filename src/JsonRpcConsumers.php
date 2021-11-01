<?php

namespace Kiri\Rpc;


use Exception;
use Http\Message\ServerRequest;
use Http\Message\Stream;
use Kiri\Consul\Agent;
use Kiri\Core\Number;
use Kiri\Kiri;
use Kiri\Pool\Pool;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
		$sf = Kiri::getDi()->get(Agent::class);

		$response = $sf->service->list('filter=Service == ' . $service);
		if ($response->getStatusCode() != 200 || $response->getBody()->getSize() <= 2) {
			throw new Exception('No microservices found [' . $service . '].');
		}
		$array = [];

		$content = json_decode($response->getBody()->getContents(), true);
		foreach ($content as $value) {
			$array[] = ['id' => $value['ID'], 'Weights' => $value['Weights']['Passing']];
		}

		if (count($array) < 2) {
			$luck = $array[0];
		} else {
			$luck = Luckdraw::luck($array, 'Weights');
		}
		return ['Address' => $luck['Address'], 'Port' => $luck['Port']];
	}

}
