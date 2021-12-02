<?php

namespace Kiri\Rpc;

use Kiri\Consul\Agent;
use Kiri\Kiri;
use ReflectionException;

class RpcManager
{


	/**
	 * @var array
	 */
	private array $_rpc = [];


	/**
	 * @param string $name
	 * @param string $class
	 * @param array $serviceConfig
	 * @return bool
	 * @throws ReflectionException
	 */
	public function add(string $name, string $class, array $serviceConfig): bool
	{
		$methods = Kiri::getDi()->getReflect($class);
		$lists = $methods->getMethods(\ReflectionMethod::IS_PUBLIC);

		if (!isset($this->_rpc[$name])) {
			$this->_rpc[$name] = ['methods' => [], 'id' => $serviceConfig['ID'], 'config' => $serviceConfig];
		}

		foreach ($lists as $reflection) {
			$methodName = $reflection->getName();

			$this->_rpc[$name]['methods'][$methodName] = [[$class, $methodName], null];
		}
		return true;
	}


	/**
	 * @return array
	 */
	public function doneList(): array
	{
		$array = [];
		foreach ($this->_rpc as $list) {
			$array[] = $list;
		}
		return $array;
	}


	/**
	 * @throws ReflectionException
	 */
	public function register()
	{
		$agent = Kiri::getDi()->get(Agent::class);
		foreach ($this->_rpc as $list) {
			var_dump($list);
			$data = $agent->service->register($list['config']);
			if ($data->getStatusCode() != 200) {
				exit($data->getBody()->getContents());
			}
		}
	}


	/**
	 * @param string $name
	 * @param string $method
	 * @return mixed
	 */
	public function get(string $name, string $method): array
	{
		return $this->_rpc[$name]['methods'][$method] ?? [null, null];
	}

}
