<?php

namespace Kiri\Rpc;

use Kiri\Exception\ConfigException;
use ReflectionException;
use Server\Abstracts\Tcp;
use Server\Constant;
use Server\ServerManager;
use Server\SInterface\OnClose;
use Server\SInterface\OnConnect;
use Server\SInterface\OnReceive;
use Server\SInterface\OnRequest;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server;


/**
 *
 */
class Service extends Tcp implements OnClose, OnConnect, OnReceive, OnRequest
{


	/**
	 * @param ServerManager $manager
	 * @param array $config
	 * @throws ConfigException
	 * @throws ReflectionException
	 */
	public static function addRpcListener(ServerManager $manager, array $config)
	{
		$config['settings']['enable_delay_receive'] = true;
		$config['settings']['enable_unsafe_event'] = true;
		$config['events'][Constant::RECEIVE] = [Service::class, 'onReceive'];
		$implements = class_implements(Service::class);
		if (in_array(OnConnect::class, $implements)) {
			$config['events'][Constant::CONNECT] = [Service::class, 'onConnect'];
		}
		if (in_array(OnClose::class, $implements)) {
			$config['events'][Constant::DISCONNECT] = [Service::class, 'onDisconnect'];
			$config['events'][Constant::CLOSE] = [Service::class, 'onClose'];
		}
		$manager->addListener(
			$config['type'], $config['host'], $config['port'], $config['mode'], $config
		);
	}


	/**
	 * @param Server $server
	 * @param int $fd
	 */
	public function onClose(Server $server, int $fd): void
	{
		// TODO: Implement onClose() method.
	}


	/**
	 * @param Server $server
	 * @param int $fd
	 */
	public function onDisconnect(Server $server, int $fd): void
	{
		// TODO: Implement onDisconnect() method.
	}


	/**
	 * @param Server $server
	 * @param int $fd
	 */
	public function onConnect(Server $server, int $fd): void
	{
		$server->confirm($fd);
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

			// TODO: Implement onReceive() method.
			[$cmd, [$body, $protocol]] = Protocol::parse($data);
		} catch(\Throwable $throwable){

		}




	}


	/**
	 * @param Request $request
	 * @param Response $response
	 */
	public function onRequest(Request $request, Response $response): void
	{
		// TODO: Implement onRequest() method.
	}
}
