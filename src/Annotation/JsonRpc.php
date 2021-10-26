<?php

namespace Kiri\Rpc\Annotation;

#[\Attribute(\Attribute::TARGET_CLASS)] class JsonRpc
{


	/**
	 * @param string $method
	 * @param string $version
	 */
	public function __construct(public string $method, public string $version = '2.0')
	{

	}


}
