<?php

namespace Kiri\Rpc;



use Throwable;

class InvalidRpcParamsException extends \Exception
{


	/**
	 * @param string $message
	 * @param int $code
	 * @param Throwable|null $previous
	 */
	public function __construct($message = "", $code = 0, Throwable $previous = null)
	{
		parent::__construct($message, -32602, $previous);
	}

}
