<?php

namespace Kiri\Rpc;

use Kiri\Pool\Pool;


/**
 *
 */
class ClientPool extends Pool
{

	const POOL_NAME = 'rpc.client.pool';


	public int $max;


	public int $min;


	public int $waite;

}
