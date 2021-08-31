<?php

namespace Kiri\Rpc;

class RpcManager
{


	private static array $_handler = [];


	/**
	 * @param $cmd
	 * @param array $handler
	 * @param string $protocol
	 */
	public static function addCmdHandler($cmd, array $handler, string $protocol)
	{
		static::$_handler[$cmd] = [$handler, $protocol];
	}


	/**
	 * @param $cmd
	 * @return array|null
	 */
	public static function getHandler($cmd): ?array
	{
		return static::$_handler[$cmd] ?? null;
	}

}
