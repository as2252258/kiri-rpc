<?php

namespace Kiri\Rpc;


use Kiri\Pool\Pool;

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
	 * @param string $method
	 * @param mixed $data
	 * @param string $version
	 */
	public function notify(string $method, mixed $data, string $version = '2.0'): void
	{

	}


	/**
	 * @param string $method
	 * @param mixed $data
	 * @param string $version
	 * @param string $id
	 * @return mixed
	 */
	public function get(string $method, mixed $data, string $version = '2.0', string $id = ''): mixed
	{

	}


	private function get_consul()
	{



	}


}
