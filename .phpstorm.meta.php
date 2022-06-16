<?php

namespace PHPSTORM_META {

    // Reflect
	use Kiri\Di\Container;
	use Kiri\Di\ContainerInterface;

	override(ContainerInterface::get(0), map('@'));
	override(Container::get(0), map('@'));
	override(Container::make(0), map('@'));
	override(Container::create(0), map('@'));
//    override(\Hyperf\Utils\Context::get(0), map('@'));
//    override(\make(0), map('@'));
    override(\di(0), map('@'));
    override(\duplicate(0), map('@'));

}
