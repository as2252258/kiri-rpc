<?php

namespace Kiri\Rpc;

use Http\Message\Response;
use Http\Message\Stream;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;


/**
 *
 */
class JsonRpcTransporter implements ClientInterface
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

		$response = $this->request($this->newClient(), $content, true);

		return (new Response())->withBody(new Stream($response));
	}


}
