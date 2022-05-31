<?php

namespace Kiri\Rpc;

use Exception;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Abstracts\Component;
use Kiri\Annotation\Inject;
use Kiri\Consul\Agent;
use Kiri\Consul\Health;
use Kiri\Message\Handler\Router;

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
	public function reRegister(): void
	{
		$service = Kiri::getDi()->get(Agent::class);
		
		$config = Config::get("rpc.consul", null, true);
		
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
			$this->reRegister();
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
	 */
	public function add(string $name, string $class, array $serviceConfig): bool
	{
		if (!isset($this->_rpc[$name])) {
//			$this->_rpc[$name] = ['id' => $serviceConfig['ID'], 'config' => $serviceConfig];
		}
		Router::addServer('rpc', static function () use ($name, $class) {
			Router::get($name, $class);
		});
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
	 * @return void
	 * @throws Kiri\Exception\ConfigException
	 */
	public function register(): void
	{
		$agent = Kiri::getDi()->get(Agent::class);
		
		$list = Config::get("rpc.consul", null, true);
		
		$agent->service->deregister($list['ID']);
		$data = $agent->service->register($list);
		if ($data->getStatusCode() != 200) {
			$this->logger->error($data->getBody());
		}
		
	}
	
}
