<?php

namespace Kiri\Rpc;

use Annotation\Inject;
use Http\Constrict\ResponseInterface;
use Http\Handler\Abstracts\HandlerManager;
use Http\Handler\Dispatcher;
use Http\Handler\Handler;
use Http\Handler\Router;
use Http\Message\ServerRequest;
use Kiri\Kiri;
use ReflectionMethod;
use Server\SInterface\OnCloseInterface;
use Server\SInterface\OnConnectInterface;
use Server\SInterface\OnReceiveInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Server;


/**
 *
 */
class RpcJsonp implements OnConnectInterface, OnReceiveInterface, OnCloseInterface
{


	#[Inject(Router::class)]
	public Router $router;


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
				return;
			}
			$result = json_encode($dispatch, JSON_UNESCAPED_UNICODE);
		} else {
			$channel = new Channel($total = count($data));
			foreach ($data as $datum) {
				$this->_execute($channel, $datum);
			}
			$result = [];
			for ($i = 0; $i < $total; $i++) {
				$params = $channel->pop();
				if (empty($params)) {
					continue;
				}
				$result[] = $params;
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
					$dispatch = null;
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
			$handler = HandlerManager::get($data['method'], 'json-rpc');
			if (is_integer($handler)) {
				throw new \Exception('Invalid Request无效请求', -32600);
			} else if (is_null($handler)) {
				throw new \Exception('Method not found', -32601);
			} else {
				return $this->handler($handler, $data);
			}
		} catch (\Throwable $throwable) {
			$code = $throwable->getCode() == 0 ? -32603 : $throwable->getCode();
			return $this->failure($code, jTraceEx($throwable), [], $data['id'] ?? null);
		}
	}


	/**
	 * @param Handler $handler
	 * @param $data
	 * @return array
	 * @throws \Exception
	 */
	private function handler(Handler $handler, $data): array
	{
		/** @var  ReflectionMethod $reflection */
		$reflection = Kiri::getDi()->getReflectMethod($handler->callback[0]::class, $handler->callback[1]);

		$params = [];
		foreach ($reflection->getParameters() as $value) {
			$params[] = $data['params'][$value->getName()] ?? null;
		}
		$handler->params = $params;

		$dispatcher = (new Dispatcher($handler, $handler->_middlewares))->handle((new ServerRequest())->withData($data['params']));
		if ($dispatcher instanceof ResponseInterface) {
			$dispatcher = json_decode($dispatcher->getBody()->getContents(), true);
		}
		return ['jsonrpc' => '2.0', 'result' => $dispatcher, 'id' => $data['id'] ?? null];
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
