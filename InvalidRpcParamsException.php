<?php

namespace Kiri\Rpc;



use JetBrains\PhpStorm\Pure;
use Throwable;

class InvalidRpcParamsException extends \Exception
{


	/**
	 * @param string $message
	 * @param int $code
	 * @param Throwable|null $previous
	 */
	#[Pure] public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
	{
		parent::__construct($message, -32602, $previous);
	}

}
