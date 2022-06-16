<?php

namespace Kiri\Rpc;


use Exception;
use Kiri;
use Kiri\Abstracts\Component;
use Kiri\Consul\Agent;
use Psr\Container\ContainerExceptionInterface;
use Kiri\Di\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Coroutine;

class Consul extends Component
{

	public Agent $agent;


	private array $config = [];


	/**
	 * @param ContainerInterface $container
	 * @param array $settings
	 * @param array $config
	 * @throws Exception
	 */
	public function __construct(public ContainerInterface $container, array $settings, array $config = [])
	{
		parent::__construct($config);
		$this->config = $settings;
	}


	/**
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function init(): void
	{
		$this->agent = $this->container->get(Agent::class);
	}


	/**
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function deregister()
	{
		if (env('environmental') != Kiri::WORKER && env('environmental_workerId') != 0) {
			return;
		}

		$agent = $this->container->get(Agent::class);

		$this->logger->debug("disconnect consul.");

		$agent->service->deregister($this->config['ID']);
		$agent->checks->deregister($this->config['Check']['CheckId']);
	}


	/**
	 * @return void
	 */
	public function service_health(): void
	{
		$info = $this->agent->service->service_health($this->config['ID']);
		if ($info->getStatusCode() == 200) {
			return;
		}
		$this->agent->service->register($this->config);
	}


	public function watches()
	{


	}


	/**
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function register(): void
	{
		$this->deregister();
		$data = $this->agent->service->register($this->config);
		if ($data->getStatusCode() != 200) {
			$this->logger->error($data->getBody());
		}
	}


}
