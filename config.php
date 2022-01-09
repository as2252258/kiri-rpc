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
	],


	'service' => [
		[
			"datacenter"     => "dc1",
			"id"             => "40e4a748-2192-161a-0510-9bf59fe950b5",
			"node"           => "FriendRpcService",
			"skipNodeUpdate" => false,
			"service"        => [
				"id"              => "redis1",
				"service"         => "FriendRpcService",
				"address"         => "172.26.221.211",
				"taggedAddresses" => [
					"lan" => [
						"address" => "127.0.0.1",
						"port"    => 8000
					],
					"wan" => [
						"address" => "172.26.221.211",
						"port"    => 80
					]
				],
				"meta"            => [
					"redis_version" => "4.0"
				],
				"port"            => 8000
			],
			"check"          => [
				"node"       => "t2.320",
				"checkId"    => "service:redis1",
				"name"       => "Redis health check",
				"Annotations"      => "Script based health check",
				"status"     => "passing",
				"serviceID"  => "redis1",
				"definition" => [
					"http"                           => "172.26.221.211:9527",
					"interval"                       => "5s",
					"timeout"                        => "1s",
					"deregisterCriticalServiceAfter" => "30s"
				],
			],
		]

	]
];
