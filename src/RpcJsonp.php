<?php

namespace Kiri\Rpc;

use Http\Constrict\RequestInterface;
use Http\Handler\Handler;
use Http\Handler\Router;
use Http\Message\ServerRequest;
use Kiri\Abstracts\Component;
use Kiri\Abstracts\Config;
use Kiri\Consul\Agent;
use Kiri\Context;
use Kiri\Events\EventProvider;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;
use Note\Inject;
use Note\Note;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;
use Server\Contract\OnCloseInterface;
use Server\Contract\OnConnectInterface;
use Server\Contract\OnReceiveInterface;
use Server\Events\OnBeforeShutdown;
use Server\Events\OnServerBeforeStart;
use Server\Events\OnTaskerStart;
use Server\Events\OnWorkerStart;
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


	#[Inject(Note::class)]
	public Note $annotation;


	#[Inject(EventProvider::class)]
	public EventProvider $eventProvider;


	private RpcManager $manager;

	/**
	 *
	 * @throws \Exception
	 */
	public function init(): void
	{
		$this->eventProvider->on(OnBeforeShutdown::class, [$this, 'onBeforeShutdown']);

		scan_directory(APP_PATH . 'rpc', 'Rpc');

		$this->eventProvider->on(OnWorkerStart::class, [$this, 'consulWatches']);
		$this->eventProvider->on(OnServerBeforeStart::class, [$this, 'register']);

		$this->manager = Kiri::getDi()->get(RpcManager::class);
	}


	/**
	 * @param OnBeforeShutdown $beforeShutdown
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function onBeforeShutdown(OnBeforeShutdown $beforeShutdown)
	{
		$doneList = $this->manager->doneList();
		$agent = $this->container->get(Agent::class);
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
		Timer::tick($async_time, static function ($timeId) {
			if (env('state', 'start') == 'exit') {
				Timer::clear($timeId);
				return;
			}
			Kiri::getDi()->get(RpcManager::class)->tick();
		});
	}


	/**
	 * @param OnServerBeforeStart $server
	 * @throws ReflectionException
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
			[$handler, $params] = $this->container->get(RpcManager::class)->get($data['service'], $data['method']);
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
	 * @param Server $server
	 * @param int $fd
	 */
	public function onClose(Server $server, int $fd): void
	{
		// TODO: Implement onClose() method.
	}
}
