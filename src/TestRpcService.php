<?php

namespace Kiri\Rpc;


use Annotation\Target;
use Http\Constrict\RequestInterface;
use Http\Handler\Controller;
use Kiri\Rpc\Annotation\JsonRpc;


/**
 *
 */
#[Target]
#[JsonRpc(method: 'test', version: '2.0')]
class TestRpcService extends Controller implements OnJsonRpcInterface
{


	/**
	 * @return int
	 */
	public function execute(): int
	{
		return $this->request->int('a') + $this->request->int('b');
	}


}
