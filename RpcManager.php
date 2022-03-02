<?php

namespace Kiri\Rpc;

use Exception;
use Kiri;
use Kiri\Abstracts\Component;
use Kiri\Annotation\Inject;
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


	#[Inject(Health::class)]
	public Health $health;


	/**
	 * @param string $serviceName
	 * @return void
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
		$service->service->register($config['config']);
	}


	/**
	 * @throws Exception
	 */
	public function tick(): void
	{
		try {
			foreach ($this->_rpc as $name => $list) {
				$this->reRegister($name);
			}
		} catch (\Throwable $throwable) {
			$this->logger->error(error_trigger_format($throwable));
		}
	}


	/**
	 * @param $serviceName
	 * @return array|null
	 */
	public function getServices($serviceName): ?array
	{
		$lists = $this->health->setQuery('passing=true')->service($serviceName);
		if ($lists->getStatusCode() != 200) {
			return null;
		}
		$body = json_decode($lists->getBody(), true);
		if (empty($body)) {
			return null;
		}
		return array_column($body, 'Service');
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
