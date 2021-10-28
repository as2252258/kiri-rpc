<?php

namespace Kiri\Rpc\Annotation;

use Annotation\Attribute;
use Kiri\Rpc\RpcManager;
use ReflectionException;

#[\Attribute(\Attribute::TARGET_CLASS)] class JsonRpc extends Attribute
{


	/**
	 * @param string $method
	 * @param string $version
	 * @param string $protocol
	 */
	public function __construct(public string $method, public string $version = '2.0', public string $protocol = 'json')
	{

	}


	/**
	 * @param mixed $class
	 * @param mixed|string $method
	 * @return mixed
	 * @throws ReflectionException
	 */
	public function execute(mixed $class, mixed $method = ''): bool
	{
		return RpcManager::add($this->method, $class);
	}


}
