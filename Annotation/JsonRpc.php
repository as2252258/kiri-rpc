<?php

namespace Kiri\Rpc\Annotation;

use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Annotation\AbstractAttribute;
use Kiri\Core\Network;
use Kiri\Exception\ConfigException;
use Kiri\Message\Handler\Router;
use Kiri\Rpc\RpcManager;
use ReflectionException;

#[\Attribute(\Attribute::TARGET_CLASS)] class JsonRpc extends AbstractAttribute
{


	private string $uniqueId = '';
	
	
	/**
	 * @param string $service
	 */
	public function __construct(public string $service)
	{
	}


	/**
	 * @param mixed $class
	 * @param mixed|string $method
	 * @return mixed
	 */
	public function execute(mixed $class, mixed $method = ''): bool
	{
		$manager = Kiri::getDi()->get(RpcManager::class);

		return $manager->add($this->service, $class);
	}

}
