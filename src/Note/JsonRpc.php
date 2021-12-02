<?php

namespace Kiri\Rpc\Note;

use Kiri\Abstracts\Config;
use Kiri\Core\Network;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;
use Kiri\Rpc\RpcManager;
use Note\Attribute;
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
	 */
	public function __construct(public string $service, public string $driver, public array $tags = [], public array $meta = [], public array $checkOptions = [])
	{
		$this->uniqueId = preg_replace('/(\w{11})(\w{4})(\w{3})(\w{8})(\w{6})/', '$1-$2-$3-$4-$5', md5(__DIR__ . '.' . md5(Network::local())));
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
		return [
			"ID"                => "rpc.json.{$this->service}." . $this->uniqueId,
			"Service"           => $this->service,
			"Address"           => Network::local(),
			"EnableTagOverride" => true,
			"TaggedAddresses"   => [
				"lan" => [
					"address" => "127.0.0.1",
					"port"    => $rpcPort
				],
				"wan" => [
					"address" => Network::local(),
					"port"    => $rpcPort
				]
			],
			"Meta"              => $this->meta,
			"Port"              => $rpcPort,
			"Check"             => [
				"CheckId"    => "service:rpc.json.{$this->service}." . $this->uniqueId,
				"Name"       => "service " . $this->service . ' health check',
				"Notes"      => "Script based health check",
				"ServiceID"  => $this->service,
				"Definition" => [
					"TCP"                            => Network::local() . ":" . Config::get('rpc.port'),
					"Interval"                       => "5s",
					"Timeout"                        => "1s",
					"DeregisterCriticalServiceAfter" => "30s"
				]
			],
		];
	}


}
