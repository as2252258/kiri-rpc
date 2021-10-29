<?php

namespace Kiri\Rpc;

use Annotation\Annotation;
use Annotation\Inject;
use Http\Handler\Router;
use Kiri\Abstracts\Component;
use Kiri\Abstracts\Config;
use Kiri\Consul\Agent;
use Kiri\Di\NoteManager;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;
use Server\SInterface\OnCloseInterface;
use Server\SInterface\OnConnectInterface;
use Server\SInterface\OnReceiveInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Server;


/**
 *
 */
class RpcJsonp extends Component implements OnConnectInterface, OnReceiveInterface, OnCloseInterface
{


	#[Inject(Router::class)]
	public Router $router;


	#[Inject(Annotation::class)]
	public Annotation $annotation;

	/**
	 *
	 * @throws \Exception
	 */
	public function init(): void
	{
		$this->annotation->read(APP_PATH . 'rpc', 'Rpc');

		$data = $this->annotation->runtime(APP_PATH . 'rpc');

		$di = Kiri::getDi();
		foreach ($data as $class) {
			foreach (NoteManager::getTargetNote($class) as $value) {
				$value->execute($class);
			}
			$methods = $di->getMethodAttribute($class);
			foreach ($methods as $method => $attribute) {
				if (empty($attribute)) {
					continue;
				}
				foreach ($attribute as $item) {
					$item->execute($class, $method);
				}
			}
		}
		$this->register();
	}


	/**
	 * @throws ConfigException
	 */
	public function register()
	{
		$config = Config::get('rpc');

		$agent = Kiri::getDi()->get(Agent::class);
		$data = $agent->service->register([
			"ID"                => $config['name'] ?? 'test-name',
			"Name"              => "redis",
			"Tags"              => ["primary", "v1"],
			"Address"           => Kiri::localhost(),
			"Port"              => $config['port'],
			"Meta"              => [
				"redis_version" => "4.0"
			],
			"EnableTagOverride" => false,
			"Check"             => [
				"DeregisterCriticalServiceAfter" => "90m",
				//				"Args"                           => ["/usr/local/bin/check_redis.py"],
				"Interval"                       => "10s",
				"Timeout"                        => "5s"
			],
			"Weights"           => [
				"Passing" => 10,
				"Warning" => 1
			]
		]);
		var_dump($data->getBody()->getContents());
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
			[$handler, $params] = RpcManager::get($data['service'], $data['method']);
			if (is_null($handler)) {
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
	 * @param array $handler
	 * @param $data
	 * @return array
	 */
	private function handler(array $handler, $data): array
	{
		$controller = Kiri::getDi()->get($handler[0]);

		$dispatcher = $controller->{$handler[1]}(...$data['params']);

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
