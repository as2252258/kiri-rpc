<?php

namespace Kiri\Rpc;

use Kiri\Core\Json;
use Kiri\Core\Number;
use Kiri\Di\Inject\Container;

abstract class AbstractRpcClient
{


	public string $service = '';


	public string $version = '';


	/**
	 * @var JsonRpcTransporterInterface
	 */
	#[Container(JsonRpcTransporterInterface::class)]
	private JsonRpcTransporterInterface $transporter;


	/**
	 * @param JsonRpcTransporterInterface $transporter
	 */
	public function setTransporter(JsonRpcTransporterInterface $transporter): void
	{
		$this->transporter = $transporter;
	}


	/**
	 * @return string
	 */
	public function getService(): string
	{
		if (empty($this->service)) {
			return get_called_class();
		}
		return $this->service;
	}


	/**
	 * @param string $method
	 * @param ...$args
	 * @return string|bool
	 */
	protected function send(string $method, ...$args): string|bool
	{
		$result = $this->transporter->push(Json::encode([
			'jsonrpc' => $this->version,
			'service' => $this->getService(),
			'method'  => $method,
			'params'  => $args,
			'id'      => Number::create(time())
		]), $this->getService());
		if (is_string($result)) {
			return json_decode($result, true);
		} else {
			return $result;
		}
	}

}
