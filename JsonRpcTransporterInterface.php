<?php

namespace Kiri\Rpc;

interface JsonRpcTransporterInterface
{

	/**
	 * @param string $content
	 * @param string $service
	 * @return string|bool
	 */
	public function push(string $content, string $service): string|bool;

}
