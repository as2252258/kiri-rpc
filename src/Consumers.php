<?php

namespace Kiri\Rpc;


use Kiri\Kiri;
use Kiri\Pool\Pool;
use SensioLabs\Consul\ServiceFactory;
use SensioLabs\Consul\Services\Agent;

/**
 *
 */
class Consumers implements OnRpcConsumerInterface
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
	 */
	public function notify(string $service, string $method, mixed $data, string $version = '2.0'): void
	{

	}


	/**
	 * @param string $service
	 * @param string $method
	 * @param mixed $data
	 * @param string $version
	 * @param string $id
	 * @return mixed
	 */
	public function get(string $service, string $method, mixed $data, string $version = '2.0', string $id = ''): mixed
	{

	}


	private function get_consul($service)
	{
		$sf = Kiri::getDi()->get(\Kiri\Consul\Agent::class);

		$content = $sf->service->service($service)->getBody()->getContents();

		$content = json_decode($content, true);
	}


}
