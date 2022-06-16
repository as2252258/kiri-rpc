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


/**
 * class RpcManager
 */
class RpcManager extends Component
{

	/**
	 * @var Health
	 */
	#[Inject(Health::class)]
	public Health $health;


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
	 * @return bool
	 */
	public function add(string $name, string $class): bool
	{
		Router::addServer('rpc', static function () use ($name, $class) {
			Router::get($name, $class);
		});
		return true;
	}


	/**
	 * @param array $config
	 * @return void
	 */
	public function register(array $config): void
	{
		$agent = Kiri::getDi()->get(Agent::class);
		$agent->checks->deregister($config['ID']);
		$agent->service->deregister($config['ID']);
		$data = $agent->service->register($config);
		if ($data->getStatusCode() != 200) {
			$this->logger->error($data->getBody());
		}

	}

}
