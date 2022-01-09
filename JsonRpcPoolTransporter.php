<?php

namespace Kiri\Rpc;

use Kiri\Annotation\Inject;
use Exception;
use Http\Message\Response;
use Http\Message\Stream;
use Kiri\Abstracts\Config;
use Kiri\Exception\ConfigException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Swoole\Coroutine\Client;

class JsonRpcPoolTransporter implements ClientInterface
{


	use TraitTransporter;


	#[Inject(ClientPool::class)]
	public ClientPool $pool;


	const POOL_NAME = 'rpc.client.pool';


	/**
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

		$this->pool->push($client, $this->config['Address'], $this->config['Port']);

		return (new Response())->withBody(new Stream($response));
	}


	/**
	 * @return Client|\Swoole\Client
	 * @throws ConfigException
	 * @throws Exception
	 */
	private function getClient(): Client|\Swoole\Client
	{
		$this->config['pool'] = Config::get('rpc.pool', ['max' => 10, 'min' => 1, 'waite' => 60]);
		return $this->pool->get($this->config, function () {
			return $this->newClient();
		});
	}


}
