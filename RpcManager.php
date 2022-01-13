<?php

namespace Kiri\Rpc;

use Exception;
use Kiri;
use Kiri\Abstracts\Component;
use Kiri\Consul\Agent;
use Kiri\Consul\Health;
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
	 * @throws Exception
	 */
	public function async($serviceName): void
	{
		$this->reRegister($serviceName);

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
	 * @param string $serviceName
	 * @return void
	 * @throws Kiri\Exception\ConfigException
	 * @throws Exception
	 */
	public function reRegister(string $serviceName)
	{
		$config = $this->_rpc[$serviceName] ?? [];
		if (empty($config)) {
			return;
		}
		$service = Kiri::getDi()->get(Agent::class);

		$info = $service->service->service_health($config['config']['ID']);
		if ($info->getStatusCode() == 200) {
			return;
		}
		$data = $service->service->register($config['config']);

		$this->logger()->info($data->getBody());
	}


	/**
	 * @throws Exception
	 */
	public function tick(): void
	{
		try {
			foreach ($this->_rpc as $name => $list) {
				$this->async($name);
			}
		} catch (\Throwable $throwable) {
			$this->logger()->error(error_trigger_format($throwable));
		}
	}


	/**
	 * @param $serviceName
	 * @return array
	 * @throws Exception
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
		$agent = Kiri::getDi()->get(Agent::class);
		foreach ($this->_rpc as $list) {
			$agent->service->deregister($list['config']['ID']);
			$data = $agent->service->register($list['config']);
			var_dump($data);
			if ($data->getStatusCode() != 200) {
				return;
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
