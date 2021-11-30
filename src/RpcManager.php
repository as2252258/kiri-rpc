<?php

namespace Kiri\Rpc;

use Kiri\Kiri;
use ReflectionException;

class RpcManager
{


	/**
	 * @var array
	 */
	private static array $_rpc = [];


	/**
	 * @param string $name
	 * @param string $class
	 * @param string $serviceId
	 * @return bool
	 * @throws ReflectionException
	 */
	public static function add(string $name, string $class, string $serviceId): bool
	{
		$methods = Kiri::getDi()->getReflect($class);
		$lists = $methods->getMethods(\ReflectionMethod::IS_PUBLIC);

		if (!isset(static::$_rpc[$name])) static::$_rpc[$name] = ['methods' => [], 'id' => $serviceId];

		foreach ($lists as $reflection) {
			$methodName = $reflection->getName();

			static::$_rpc[$name]['methods'][$methodName] = [[$class, $methodName], null];
		}
		return true;
	}


	/**
	 * @return array
	 */
	public static function doneList(): array
	{
		$array = [];
		foreach (static::$_rpc as $list) {
			$array[] = $list['id'];
		}
		return $array;
	}


	/**
	 * @param string $name
	 * @param string $method
	 * @return mixed
	 */
	public static function get(string $name, string $method): array
	{
		return static::$_rpc[$name]['methods'][$method] ?? [null, null];
	}

}
