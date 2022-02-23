<?php

namespace Kiri\Rpc;

use Kiri;
use Kiri\Abstracts\Component;
use Kiri\Abstracts\Config;
use Kiri\Annotation\Annotation;
use Kiri\Annotation\Inject;
use Kiri\Consul\Agent;
use Kiri\Context;
use Kiri\Exception\ConfigException;
use Kiri\Message\Constrict\RequestInterface;
use Kiri\Message\Handler\Handler;
use Kiri\Message\Handler\Router;
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


	private RpcManager $manager;


	private int $timerId;


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
			[$handler, $params] = $this->getContainer()->get(RpcManager::class)->get($data['service'], $data['method']);
			if (is_null($handler)) {
				throw new \Exception('Method not found', -32601);
			} else {
				Context::setContext(RequestInterface::class, $this->createServerRequest($params));

				return $this->handler($handler);
			}
		} catch (\Throwable $throwable) {
			$code = $throwable->getCode() == 0 ? -32603 : $throwable->getCode();
			return $this->failure($code, jTraceEx($throwable), [], $data['id'] ?? null);
		}
	}


	/**
	 * @param $params
	 * @return ServerRequestInterface
	 * @throws \Exception
	 */
	private function createServerRequest($params): ServerRequestInterface
	{
		return (new ServerRequest())->withParsedBody($params);
	}


	/**
	 * @param Handler $handler
	 * @return array
	 */
	private function handler(Handler $handler): array
	{
		return [
			'jsonrpc' => '2.0',
			'result'  => call_user_func($handler->callback, ...$handler->params),
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
	 * @param int $fd
	 */
	public function onClose(int $fd): void
	{
		// TODO: Implement onClose() method.
	}
}
