<?php

namespace Kiri\Rpc;

use Kiri\Consul\Agent;
use Kiri\Consul\Health;
use Kiri\Kiri;
use ReflectionException;

class RpcManager
{


	/**
	 * @var array
	 */
	private array $_rpc = [];


	private array $_services = [];


	/**
	 * @param $serviceName
	 * @return array
	 * @throws ReflectionException
	 */
	public function async($serviceName): array
	{
		$lists = Kiri::getDi()->get(Health::class)->setQuery('passing=true')->service($serviceName);
		if ($lists->getStatusCode() != 200) {
			return [];
		}
		var_dump($lists->getBody());
		$body = json_decode($lists->getBody(), true);
		if (empty($body) || !is_array($body)) {
			return $this->_services = [];
		}
		return $this->_services[$serviceName] = array_column($body, 'service');
	}


	/**
	 * @throws ReflectionException
	 */
	public function tick(): void
	{
		foreach ($this->_rpc as $name => $list) {
			$this->async($name);
		}
	}


	/**
	 * @param $serviceName
	 * @return array
	 */
	public function getServices($serviceName): array
	{
		return $this->_services[$serviceName] ?? [];
	}


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
			$data = $agent->service->register($list['config']);
			if ($data->getStatusCode() != 200) {
				exit($data->getBody());
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
