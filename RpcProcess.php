<?php

namespace Kiri\Rpc;

use Kiri\Di\Context;
use Kiri\Server\Abstracts\BaseProcess;
use Swoole\Coroutine;
use Swoole\Process;

class RpcProcess extends BaseProcess
{


    /**
     * @return string
     */
    public function getName(): string
    {
        return "Rpc Manager";
    }


    /**
     * @param Process|null $process
     * @return void
     */
    public function process(?Process $process): void
    {

    }


    /**
     * @return $this
     */
    public function onSigterm(): static
    {
        // TODO: Implement onSigterm() method.
        if (Context::inCoroutine()) {
            Coroutine::create(fn() => $this->onShutdown(Coroutine::waitSignal(SIGTERM | SIGINT)));
        } else {
            pcntl_signal(SIGTERM, [$this, 'onStop']);
            pcntl_signal(SIGINT, [$this, 'onStop']);
        }
        return $this;
    }
}