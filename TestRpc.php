<?php

use Kiri\Rpc\AbstractRpcClient;


class TestRpc extends AbstractRpcClient
{

	public string $service = '';


	/**
	 * @param $data
	 * @param $nba
	 * @return mixed
	 */
	public function test($data, $nba): mixed
	{
		$resp = $this->send(__FUNCTION__, $data, $nba);

		return json_decode($resp, true);
	}


}
