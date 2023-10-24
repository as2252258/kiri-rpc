<?php

namespace Kiri\Rpc;

use Exception;
use Kiri\Di\Inject\Container;
use Kiri\Exception\ConfigException;
use ReflectionException;

class JsonRpcPoolTransporter implements JsonRpcTransporterInterface
{


	use TraitTransporter;


	#[Container(ClientPool::class)]
	public ClientPool $pool;


    /**
     * @param string $content
     * @param string $service
     * @return string|bool
     * @throws RpcServiceException
     * @throws ReflectionException
     */
	public function push(string $content, string $service): string|bool
	{
		$client = $this->get_consul($service)->getClient();

		$response = $this->request($client, $content);

		$this->pool->push($client, $this->config['Address'], $this->config['Port']);

		return $response;
	}


	/**
     * @throws Exception
	 */
	private function getClient()
	{
		return $this->pool->get($this->config);
	}


}
