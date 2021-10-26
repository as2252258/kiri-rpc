<?php


use Kiri\Rpc\RpcJsonp;
use Kiri\Rpc\TestRpcService;
use Server\Constant;

return [
	'rpc' => [
		'name'     => 'json-rpc',
		'type'     => Constant::SERVER_TYPE_BASE,
		'mode'     => SWOOLE_SOCK_TCP,
		'host'     => '0.0.0.0',
		'port'     => 9526,
		'settings' => [

		],
		'events'   => [
			Constant::RECEIVE => [RpcJsonp::class, 'onReceive']
		],


		'consumers' => [
			'class'    => TestRpcService::class,
			'name'     => 'test-rpc',
			'package'  => 'test',
			'register' => [
				'host' => '',
				'port' => ''
			]
		]
	]
];
