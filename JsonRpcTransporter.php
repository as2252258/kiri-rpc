<?php

namespace Kiri\Rpc;

use Kiri\Message\Response;
use Kiri\Message\Stream;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;


/**
 *
 */
class JsonRpcTransporter implements RpcClientInterface
{


	use TraitTransporter;


	/**
	 * @param RequestInterface $request
	 * @return ResponseInterface
	 * @throws \Exception
	 */
	public function sendRequest(RequestInterface $request): ResponseInterface
	{
		$content = $request->getBody()->getContents();

		$body = $this->request($this->newClient(), $content);

		$response = \Kiri::getDi()->get(ResponseInterface::class);

		return $response->withBody(new Stream($body));
	}


}
