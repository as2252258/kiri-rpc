<?php

namespace Kiri\Rpc;

use Exception;
use Kiri;
use Kiri\Abstracts\Component;
use Kiri\Abstracts\Config;
use Kiri\Annotation\Annotation;
use Kiri\Annotation\Inject;
use Kiri\Consul\Agent;
use Kiri\Context;
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
use Kiri\Server\Events\OnTaskerStart;
use Kiri\Server\Events\OnWorkerExit;
use Kiri\Server\Events\OnWorkerStart;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Server;
use Swoole\Timer;

/**
 *
 */
class RpcJsonp extends Component implements OnConnectInterface, OnReceiveInterface, OnCloseInterface
{


	#[Inject(Router::class)]
	public Router $router;


	#[Inject(Annotation::class)]
	public Annotation $annotation;


	public RpcManager $manager;


	private int $timerId;


	public RouterCollector $collector;


	/**
	 * @param ContainerInterface $container
	 * @param array $config
	 * @throws Exception
	 */
	public function __construct(public ContainerInterface $container, array $config = [])
	{
		parent::__construct($config);
	}


	/**
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 */
	public function init(): void
	{
		$provider = $this->getEventProvider();
		$provider->on(OnBeforeShutdown::class, [$this, 'onBeforeShutdown']);

		scan_directory(APP_PATH . 'rpc', 'app\Rpc');

		$provider->on(OnWorkerStart::class, [$this, 'consulWatches']);
		$provider->on(OnWorkerExit::class, [$this, 'onWorkerExit']);
		$provider->on(OnServerBeforeStart::class, [$this, 'register']);

		$this->manager = Kiri::getDi()->get(RpcManager::class);

		$this->collector = $this->container->get(DataGrip::class)->get('rpc');
	}


	/**
	 * @param OnBeforeShutdown $beforeShutdown
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function onBeforeShutdown(OnBeforeShutdown $beforeShutdown)
	{
		$doneList = $this->manager->doneList();
		$agent = $this->getContainer()->get(Agent::class);
		foreach ($doneList as $value) {
			$agent->service->deregister($value['config']['ID']);
			$agent->checks->deregister($value['config']['Check']['CheckId']);
		}
	}


	/**
	 * @param OnWorkerStart|OnTaskerStart $server
	 * @throws ConfigException
	 */
	public function consulWatches(OnWorkerStart|OnTaskerStart $server)
	{
		if ($server->workerId != 0) {
			return;
		}
		$async_time = (int)Config::get('consul.async_time', 1000);
		$this->timerId = Timer::tick($async_time, static function () {
			Kiri::getDi()->get(RpcManager::class)->tick();
		});
	}


	/**
	 * @param OnWorkerExit $exit
	 * @return void
	 */
	public function onWorkerExit(OnWorkerExit $exit)
	{
		Timer::clear($this->timerId);
	}


	/**
	 * @param OnServerBeforeStart $server
	 */
	public function register(OnServerBeforeStart $server)
	{
		$this->manager->register();
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
		$data = json_decode($data, true);
		if (is_null($data)) {
			$this->failure(-32700, 'Parse error语法解析错误');
		} else if (!isset($data['jsonrpc']) || !isset($data['method']) || $data['jsonrpc'] != '2.0') {
			$this->failure(-32600, 'Invalid Request无效请求');
		} else {
			$this->batchDispatch($server, $fd, $data);
		}
	}


	/**
	 * @param Server $server
	 * @param int $fd
	 * @param array $data
	 * @return void
	 */
	private function batchDispatch(Server $server, int $fd, array $data): void
	{
		if (isset($data['jsonrpc'])) {
			$dispatch = $this->dispatch($data);
			if (!isset($data['id'])) {
				$dispatch = [1];
			}
			$result = json_encode($dispatch, JSON_UNESCAPED_UNICODE);
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
		$server->send($fd, json_encode($result, JSON_UNESCAPED_UNICODE));
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
				throw new Exception('Method not found', -32601);
			} else {
				$controller = $handler->callback[0];
				if (!method_exists($controller, $data['method'])) {
					throw new Exception('Method not found', -32601);
				}
				$params = $this->container->getMethodParameters($controller::class, $data['method']);

				Context::setContext(RequestInterface::class, $this->createServerRequest($params));

				return $this->handler($controller, $data['method'], $params);
			}
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
	 * @param \Swoole\WebSocket\Server $server
	 * @param int $fd
	 * @return void
	 */
	public function onClose(\Swoole\WebSocket\Server $server, int $fd): void
	{
		// TODO: Implement onClose() method.
	}
}
