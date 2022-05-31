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


    private int $timerId;


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
     * @throws ReflectionException
     */
    public function init(): void
    {
        $this->eventProvider->on(OnBeforeShutdown::class, [$this, 'onBeforeShutdown']);

        scan_directory(APP_PATH . 'rpc', 'app\Rpc');

        $this->eventProvider->on(OnWorkerStart::class, [$this, 'consulWatches']);
        $this->eventProvider->on(OnWorkerExit::class, [$this, 'onWorkerExit']);
        $this->eventProvider->on(OnServerBeforeStart::class, [$this, 'register']);

        $this->collector = $this->dataGrip->get('rpc');
    }


    /**
     * @param OnBeforeShutdown $beforeShutdown
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface|ConfigException
     */
    public function onBeforeShutdown(OnBeforeShutdown $beforeShutdown): void
    {
		if ($beforeShutdown->server->worker_id != 0) {
			return;
		}
        $agent = $this->container->get(Agent::class);
	    $value = Config::get("rpc.consul", []);
		if (empty($value)) {
			return;
		}
		
		$this->logger->debug("disconnect consul.");
		
	    $agent->service->deregister($value['ID']);
	    $agent->checks->deregister($value['Check']['CheckId']);
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
    public function onWorkerExit(OnWorkerExit $exit): void
    {
        Timer::clear($this->timerId);
    }
	
	
	/**
	 * @param OnServerBeforeStart $server
	 * @throws ConfigException
	 */
    public function register(OnServerBeforeStart $server)
    {
		$consumers = Config::get("rpc.consumers", []);
		if (!empty($consumers)) {
			$manager = Kiri::getDi()->get(RpcManager::class);
			foreach ($consumers as $service => $consumer) {
				$manager->add($service, $consumer);
			}
		}
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
            $result = json_encode($result, JSON_UNESCAPED_UNICODE);
        }
        $server->send($fd, $result);
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
            $params = $this->container->getMethodParameters($controller::class, $data['method']);

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
            'result' => $result,
            'id' => $data['id'] ?? null
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
            'error' => [
                'code' => $code,
                'message' => $message,
                'data' => $data
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
