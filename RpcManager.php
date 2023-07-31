<?php

namespace Kiri\Rpc;

use Etcd\Client;
use Kiri;
use Kiri\Abstracts\Component;
use PhpParser\Node\Stmt\Return_;


/**
 * class RpcManager
 */
class RpcManager extends Component
{


    protected array $services = [];


    /**
     * @return void
     */
    public function watch(): void
    {
        $data = new Client('','');
    }


    /**
     * @param $service
     * @return mixed
     */
    public function getServices($service): mixed
    {
        return $this->services[$service] ?? null;
    }

}
