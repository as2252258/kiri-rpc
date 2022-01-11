<?php

namespace Kiri\Rpc;

use Kiri\Abstracts\Component;
use Kiri\Consul\Agent;
use Kiri\Consul\Health;
use Kiri\Kiri;
use Kiri\Message\Handler\Handler;
use ReflectionException;

class RpcManager extends Component
{


	/**
	 * @var array
	 */
	private array $_rpc = [];


	/**
	 * @param $serviceName
	 * @return void
	 * @throws ReflectionException
	 * @throws \Exception
	 */
	public function async($serviceName): void
	{
		$lists = Kiri::getDi()->get(Health::class)->setQuery('passing=true')->service($serviceName);
		if ($lists->getStatusCode() != 200) {
			return;
		}
		$body = json_decode($lists->getBody(), true);
		$file = storage('.rpc.clients.' . md5($serviceName), 'rpc');
		if (!empty($body) && is_array($body)) {
			file_put_contents($file, json_encode(array_column($body, 'Service')), LOCK_EX);
		} else {
			file_put_contents($file, json_encode([]), LOCK_EX);
		}
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
	 * @throws \Exception
	 */
	public function getServices($serviceName): array
	{
		$file = storage('.rpc.clients.' . md5($serviceName), 'rpc');
		if (!file_exists($file) || filesize($file) < 10) {
			$this->async($serviceName);
		}
		$content = json_decode(file_get_contents($file), true);
		if (empty($content) || !is_array($content)) {
			return [];
		}
		return $content;
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
			if ($reflection->getDeclaringClass() != $class) {
				continue;
			}
			$methodName = $reflection->getName();
			$this->_rpc[$name]['methods'][$methodName] = [new Handler('/', [$class, $methodName]), null];
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
	 */
	public function register()
	{
		try {
			$agent = Kiri::getDi()->get(Agent::class);
			foreach ($this->_rpc as $list) {
				var_dump($agent->service->deregister($list['config']['ID']));
				var_dump($agent->checks->deregister($list['config']['Check']['CheckId']));
				$data = $agent->service->register($list['config']);
				if ($data->getStatusCode() != 200) {
					return;
				}
			}
		} catch (\Throwable $exception) {
			var_dump($exception);
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
