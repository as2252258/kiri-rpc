<?php

namespace Kiri\Rpc\Annotation;

use Kiri\Abstracts\Config;
use Kiri\Core\Network;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;
use Kiri\Rpc\RpcManager;
use Kiri\Annotation\Attribute;
use ReflectionException;

#[\Attribute(\Attribute::TARGET_CLASS)] class JsonRpc extends Attribute
{


	private string $uniqueId = '';


	/**
	 * @param string $service
	 * @param string $driver
	 * @param array $tags
	 * @param array $meta
	 * @param array $checkOptions
	 * @param string $checkUrl
	 */
	public function __construct(public string $service, public string $driver, public array $tags = [], public array $meta = [], public array $checkOptions = [], public string $checkUrl = '')
	{
		$this->uniqueId = preg_replace('/(\w{11})(\w{4})(\w{3})(\w{8})(\w{6})/', '$1-$2-$3-$4-$5', md5(__DIR__ . 'Annotation' . md5(Network::local())));
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
		return Kiri::getDi()->get(RpcManager::class)->add($this->service, $class, $this->create());
	}


	/**
	 * @throws ConfigException
	 */
	protected function create(): array
	{
		$rpcPort = Config::get('rpc.port');
		if (empty($this->checkUrl)) {
			$this->checkUrl = Network::local() . ":" . Config::get('rpc.port');
		}
		$defaultConfig = [
			"ID"                => "rpc.json.{$this->service}." . $this->uniqueId,
			"Name"              => $this->service,
			"EnableTagOverride" => false,
			"TaggedAddresses"   => [
				"lan_ipv4" => [
					"address" => "127.0.0.1",
					"port"    => $rpcPort
				],
				"wan_ipv4" => [
					"address" => Network::local(),
					"port"    => $rpcPort
				]
			],
			"Check"             => [
				"CheckId"                        => "service:rpc.json.{$this->service}." . $this->uniqueId,
				"Name"                           => "service " . $this->service . ' health check',
				"Annotations"                          => "Script based health check",
				"ServiceID"                      => $this->service,
				"TCP"                            => $this->checkUrl,
				"Interval"                       => "5s",
				"Timeout"                        => "1s",
				"DeregisterCriticalServiceAfter" => "30s"
			],
		];
		if (!empty($this->meta)) {
			$defaultConfig["Meta"] = $this->meta;
		}
		if (!empty($this->tags)) {
			$defaultConfig["tags"] = $this->tags;
		}
		return $defaultConfig;
	}


}
