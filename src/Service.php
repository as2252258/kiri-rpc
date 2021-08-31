<?php

namespace Kiri\Rpc;

use Server\SInterface\OnClose;
use Server\SInterface\OnConnect;
use Server\SInterface\OnPacket;
use Server\SInterface\OnReceive;
use Server\SInterface\OnRequest;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server;


/**
 *
 */
class Service implements OnClose, OnConnect, OnReceive, OnPacket, OnRequest
{


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
		// TODO: Implement onConnect() method.
	}


	/**
	 * @param Server|\Server\Abstracts\Server $server
	 * @param string $data
	 * @param array $clientInfo
	 */
	public function onPacket(Server|\Server\Abstracts\Server $server, string $data, array $clientInfo): void
	{
		// TODO: Implement onPacket() method.
	}


	/**
	 * @param Server $server
	 * @param int $fd
	 * @param int $reactor_id
	 * @param string $data
	 */
	public function onReceive(Server $server, int $fd, int $reactor_id, string $data): void
	{
		// TODO: Implement onReceive() method.
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
