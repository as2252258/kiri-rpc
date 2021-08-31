<?php

namespace Kiri\Rpc;

class Protocol
{

	const SPLIT_STRING = "\r\r\n\n";


	/**
	 * @param string $data
	 * @return array|null
	 */
	public static function parse(string $data)
	{
		if (!str_contains($data, Protocol::SPLIT_STRING)) {
			return null;
		}

		[$cmd, $requestBody] = explode(Protocol::SPLIT_STRING, $data);

		return [$cmd, json_decode($requestBody, true)];
	}


	/**
	 * @param string $cmd
	 * @param array $data
	 * @return string
	 */
	public static function create(string $cmd, array $data): string
	{
		return implode("\r\r\n\n", [$cmd, json_encode($data, JSON_UNESCAPED_UNICODE)]);
	}

}
