<?php

namespace Kiri\Rpc;

use Exception;


/**
 *
 */
class JsonRpcTransporter implements JsonRpcTransporterInterface
{


	use TraitTransporter;


	/**
	 * @param string $content
	 * @param string $service
	 * @return string|bool
	 * @throws RpcServiceException
	 * @throws Exception
	 */
	public function push(string $content, string $service): string|bool
	{
		$client = $this->get_consul($service)->newClient();

		$body = $this->request($client, $content);

		$client->close();

		return $body;
	}


}
