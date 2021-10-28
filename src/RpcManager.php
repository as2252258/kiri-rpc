<?php

namespace Kiri\Rpc;

use Kiri\Kiri;
use ReflectionException;

class RpcManager
{

	private static array $_rpc = [];


	/**
	 * @param string $name
	 * @param string $class
	 * @return bool
	 * @throws ReflectionException
	 */
	public static function add(string $name, string $class): bool
	{
		$methods = Kiri::getDi()->getReflect($class);
		$lists = $methods->getMethods(\ReflectionMethod::IS_PUBLIC);

		if (!isset(static::$_rpc[$name])) static::$_rpc[$name] = [];

		foreach ($lists as $reflection) {
			$methodName = $reflection->getName();

			static::$_rpc[$name][$methodName] = [[$class, $methodName], null];
		}
		return true;
	}


	/**
	 * @param string $name
	 * @param string $method
	 * @return mixed
	 */
	public static function get(string $name, string $method): array
	{
		return static::$_rpc[$name][$method] ?? [null, null];
	}

}
