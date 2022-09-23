<?php

namespace Kiri\Rpc;

use Kiri\Annotation\Inject;
use Exception;
use Kiri\Message\Response;
use Kiri\Message\Stream;
use Kiri\Abstracts\Config;
use Kiri\Exception\ConfigException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Swoole\Coroutine\Client;

class JsonRpcPoolTransporter implements JsonRpcTransporterInterface
{


	use TraitTransporter;


	#[Inject(ClientPool::class)]
	public ClientPool $pool;


	/**
	 * @param string $content
	 * @param string $service
	 * @return string|bool
	 * @throws ConfigException|RpcServiceException
	 */
	public function push(string $content, string $service): string|bool
	{
		$client = $this->get_consul($service)->getClient();

		$response = $this->request($client, $content);

		$this->pool->push($client, $this->config['Address'], $this->config['Port']);

		return $response;
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
