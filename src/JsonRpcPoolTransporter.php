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
		$config = Config::get('rpc.pool', null);

		$this->pool = Kiri::getDi()->get(ClientPool::class, [], $config);
		$this->pool->initConnections(self::POOL_NAME, true, $config['max']);
	}


	/**
	 * @param RequestInterface $request
	 * @return ResponseInterface
	 * @throws Exception
	 */
	public function sendRequest(RequestInterface $request): ResponseInterface
	{
		$content = $request->getBody()->getContents();
		return (new Response())->withBody(
			new Stream($this->request($this->getClient(), $content))
		);
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
