<?php

namespace Kiri\Rpc\Annotation;

use Annotation\Attribute;
use Kiri\Abstracts\Config;
use Kiri\Consul\Agent;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;
use Kiri\Rpc\RpcManager;
use ReflectionException;

#[\Attribute(\Attribute::TARGET_CLASS)] class JsonRpc extends Attribute
{


	/**
	 * @param string $service
	 * @param string $driver
	 * @param array $checkOptions
	 */
	public function __construct(public string $service, public string $driver, public array $checkOptions = [
		"DeregisterCriticalServiceAfter" => "1m",
		"Http"                           => "http://127.0.0.1:9527",
		"Interval"                       => "1s",
		"Timeout"                        => "1s"
	])
	{

	}


	/**
	 * @param mixed $class
	 * @param mixed|string $method
	 * @return mixed
	 * @throws ReflectionException
	 * @throws ConfigException
	 */
	public function execute(mixed $class, mixed $method = ''): bool
	{
//		$default = $this->create();
//		$agent = Kiri::getDi()->get(Agent::class);
//		$data = $agent->service->register($default);
//		if ($data->getStatusCode() != 200) {
//			exit($data->getBody()->getContents());
//		}
//		return RpcManager::add($this->service, $class, $default['id']);
        return true;
	}



	/**
	 * @throws ConfigException
	 */
	protected function create(): array
	{
		$content = current(swoole_get_local_ip());
		return [
			"id"                => "rpc.json.{$this->service}." . md5(__DIR__ . '.' . md5($content)),
			"name"              => $this->service,
			"address"           => $content,
			"port"              => 9526,
			"enableTagOverride" => true,
			"check"             => [
				"DeregisterCriticalServiceAfter" => "1m",
				"TCP"                            => $content . ":" . Config::get('rpc.port'),
				"Interval"                       => "1s",
				"Timeout"                        => "1s"
			]
		];
	}


}
