<?php

namespace Kiri\Rpc;


use Http\Constrict\RequestInterface;
use Kiri\Rpc\Annotation\JsonRpc;


/**
 *
 */
#[JsonRpc(method: 'test.service', version: '2.0')]
class TestRpcService implements OnJsonRpcInterface
{


	/**
	 * @var RequestInterface
	 */
	public RequestInterface $request;


	/**
	 * @param int $i
	 * @param int $b
	 * @return int
	 */
	public function execute(int $i, int $b): int
	{
		return $i + $b;
	}
}
