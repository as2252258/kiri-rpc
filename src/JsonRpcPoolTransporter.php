<?php

namespace Kiri\Rpc;

use Exception;
use Http\Message\Response;
use Http\Message\Stream;
use Kiri\Abstracts\Config;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Swoole\Coroutine\Client;

class JsonRpcPoolTransporter implements ClientInterface
{


	use TraitTransporter;


	public ClientPool $pool;


	const POOL_NAME = 'rpc.client.pool';


	/**
	 * @throws ConfigException
	 */
	public function init()
	{
	}


	/**
	 * @param RequestInterface $request
	 * @return ResponseInterface
	 * @throws Exception
	 */
	public function sendRequest(RequestInterface $request): ResponseInterface
	{
		$content = $request->getBody()->getContents();

		$response = $this->request($client = $this->getClient(), $content, false);

		$this->pool->push($client, $this->config['ServiceAddress'], $this->config['ServicePort']);

		return (new Response())->withBody(new Stream($response));
	}


	/**
	 * @return Client|\Swoole\Client
	 * @throws ConfigException
	 * @throws Exception
	 */
	private function getClient(): Client|\Swoole\Client
	{
		return $this->pool->get(self::POOL_NAME, function () {
			return $this->newClient();
		});
	}


}
