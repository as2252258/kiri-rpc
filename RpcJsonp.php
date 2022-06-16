<?php

namespace Kiri\Rpc;

use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Kiri;
use Kiri\Abstracts\Component;
use Kiri\Abstracts\Config;
use Kiri\Annotation\Annotation;
use Kiri\Consul\Agent;
use Kiri\Context;
use Kiri\Events\EventProvider;
use Kiri\Exception\ConfigException;
use Kiri\Message\Constrict\RequestInterface;
use Kiri\Message\Handler\DataGrip;
use Kiri\Message\Handler\Router;
use Kiri\Message\Handler\RouterCollector;
use Kiri\Message\ServerRequest;
use Kiri\Server\Contract\OnCloseInterface;
use Kiri\Server\Contract\OnConnectInterface;
use Kiri\Server\Contract\OnReceiveInterface;
use Kiri\Server\Events\OnBeforeShutdown;
use Kiri\Server\Events\OnServerBeforeStart;
use Psr\Container\ContainerExceptionInterface;
use Kiri\Di\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Server;

/**
 *
 */
class RpcJsonp extends Component implements OnConnectInterface, OnReceiveInterface, OnCloseInterface
{


	private array $consul = [];


	/**
	 * @param ContainerInterface $container
	 * @param Router $router
	 * @param Annotation $annotation
	 * @param DataGrip $dataGrip
	 * @param RpcManager $manager
	 * @param RouterCollector $collector
	 * @param EventProvider $eventProvider
	 * @param array $config
	 * @throws Exception
	 */
	public function __construct(public ContainerInterface $container,
	                            public Router             $router,
	                            public Annotation         $annotation,
	                            public DataGrip           $dataGrip,
	                            public RpcManager         $manager,
	                            public RouterCollector    $collector,
	                            public EventProvider      $eventProvider,
	                            array                     $config = [])
	{
		parent::__construct($config);
	}


	/**
	 * @return void
	 * @throws ReflectionException|ConfigException
	 */
	public function init(): void
	{
		$this->eventProvider->on(OnBeforeShutdown::class, [$this, 'onBeforeShutdown']);
		scan_directory(APP_PATH . 'rpc', 'app\Rpc');
		$this->consul = Config::get('rpc.consul', null);
		if (!empty($this->consul)) {
			$this->eventProvider->on(OnServerBeforeStart::class, [$this, 'register']);
		}
		$this->collector = $this->dataGrip->get('rpc');
	}


	/**
	 * @param OnBeforeShutdown $beforeShutdown
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function onBeforeShutdown(OnBeforeShutdown $beforeShutdown): void
	{
		if (env('environmental') != Kiri::WORKER && env('environmental_workerId') != 0) {
			return;
		}

		$agent = $this->container->get(Agent::class);

		$this->logger->debug("disconnect consul.");

		$agent->service->deregister($this->consul['ID']);
		$agent->checks->deregister($this->consul['Check']['CheckId']);
	}


	/**
	 * @param OnServerBeforeStart $server
	 * @throws ConfigException
	 */
	public function register(OnServerBeforeStart $server)
	{
		$consumers = Config::get("rpc.consumers", []);
		if (!empty($consumers)) {
			foreach ($consumers as $service => $consumer) {
				$this->manager->add($service, $consumer);
			}
		}
		$this->manager->register($this->consul);
	}


	/**
	 * @param Server $server
	 * @param int $fd
	 */
	public function onConnect(Server $server, int $fd): void
	{
		// TODO: Implement onConnect() method.
	}


	/**
	 * @param Server $server
	 * @param int $fd
	 * @param int $reactor_id
	 * @param string $data
	 */
	public function onReceive(Server $server, int $fd, int $reactor_id, string $data): void
	{
		try {
			$data = json_decode($data, true);
			if (!is_array($data)) {
				throw new Exception('Parse error语法解析错误', -32700);
			}
			if (!isset($data['jsonrpc']) || !isset($data['method']) || $data['jsonrpc'] != '2.0') {
				throw new Exception('Invalid Request无效请求', -32600);
			}
			$server->send($fd, $this->batchDispatch($data));
		} catch (\Throwable $throwable) {
			$this->logger->error('JsonRpc: ' . $throwable->getMessage());
			$server->send($fd, $this->failure(-32700, 'Parse error语法解析错误'));
		}
	}


	/**
	 * @param array $data
	 * @return string|bool
	 */
	private function batchDispatch(array $data): string|bool
	{
		if (isset($data['jsonrpc'])) {
			$result = $this->dispatch($data);
			if (!isset($data['id'])) {
				$result = [1];
			}
		} else {
			$channel = new Channel($total = count($data));
			foreach ($data as $datum) {
				$this->_execute($channel, $datum);
			}
			$result = [];
			for ($i = 0; $i < $total; $i++) {
				$result[] = $channel->pop();
			}
		}
		return json_encode($result, JSON_UNESCAPED_UNICODE);
	}


	/**
	 * @param $channel
	 * @param $datum
	 */
	private function _execute($channel, $datum)
	{
		Coroutine::create(function () use ($channel, $datum) {
			if (empty($datum) || !isset($datum['jsonrpc'])) {
				$channel->push($this->failure(-32700, 'Parse error语法解析错误'));
			} else if (!isset($datum['method'])) {
				$channel->push($this->failure(-32700, 'Parse error语法解析错误'));
			} else {
				$dispatch = $this->dispatch($datum);
				if (!isset($dispatch['id'])) {
					$dispatch = [1];
				}
				$channel->push($dispatch);
			}
		});
	}


	/**
	 * @param $data
	 * @return array
	 */
	private function dispatch($data): array
	{
		try {
			$handler = $this->collector->find($data['service'], 'GET');
			if (is_integer($handler) || is_null($handler)) {
				throw new Exception('Handler not found', -32601);
			}
			$controller = $this->container->get($handler->callback[0]);
			if (!method_exists($controller, $data['method'])) {
				throw new Exception('Method not found', -32601);
			}
			$params = $this->container->getArgs($controller::class, $data['method']);

			Context::setContext(RequestInterface::class, $this->createServerRequest($params));

			return $this->handler($controller, $data['method'], $params);
		} catch (\Throwable $throwable) {
			$code = $throwable->getCode() == 0 ? -32603 : $throwable->getCode();
			return $this->failure($code, jTraceEx($throwable), [], $data['id'] ?? null);
		}
	}


	/**
	 * @param $params
	 * @return ServerRequestInterface
	 * @throws Exception
	 */
	private function createServerRequest($params): ServerRequestInterface
	{
		return (new ServerRequest())->withParsedBody($params);
	}


	/**
	 * @param $controller
	 * @param string $method
	 * @param $params
	 * @return array
	 */
	#[ArrayShape([])]
	private function handler($controller, string $method, $params): array
	{
		$result = call_user_func([$controller, $method], ...$params);
		return [
			'jsonrpc' => '2.0',
			'result'  => $result,
			'id'      => $data['id'] ?? null
		];
	}


	/**
	 * @param $code
	 * @param $message
	 * @param array $data
	 * @param null $id
	 * @return array
	 */
	#[ArrayShape([])]
	protected function failure($code, $message, array $data = [], $id = null): array
	{
		$error = [
			'jsonrpc' => '2.0',
			'error'   => [
				'code'    => $code,
				'message' => $message,
				'data'    => $data
			]
		];
		if (!is_null($id)) {
			$error['id'] = $id;
		}
		return $error;
	}


	/**
	 * @param int $fd
	 * @return void
	 */
	public function onClose(int $fd): void
	{
		// TODO: Implement onClose() method.
	}
}
