<?php

namespace Kiri\Rpc;


use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Kiri\Message\ServerRequest;
use Kiri\Message\Stream;
use Kiri\Core\Number;
use Kiri;
use Kiri\Pool\Pool;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Kiri\Annotation\Inject;

/**
 *
 */
abstract class JsonRpcConsumers implements OnRpcConsumerInterface
{


    /**
     * @var Pool
     */
    public Pool $pool;


    /**
     * @var RpcManager
     */
    #[Inject(RpcManager::class)]
    public RpcManager $manager;


    /**
     * @var RpcClientInterface
     */
    #[Inject(RpcClientInterface::class)]
    public RpcClientInterface $client;


    protected string $name = '';


    /**
     * @param string $method
     * @param mixed $data
     * @param string $version
     * @throws Exception
     * @throws ClientExceptionInterface
     */
    public function notify(string $method, mixed $data, string $version = '2.0'): void
    {
        $this->client->withConfig($this->get_consul($this->name))->sendRequest(
            $this->requestBody([
                'jsonrpc' => $version,
                'service' => str_starts_with($this->name, '/') ? $this->name : '/' . $this->name,
                'method' => $method,
                'params' => $data,
            ])
        );
    }


    /**
     * @param array $data
     * @return ServerRequestInterface
     */
    private function requestBody(array $data): ServerRequestInterface
    {
        $server = Kiri::getDi()->get(ServerRequest::class);
        return $server->withBody(new Stream(json_encode($data)));
    }


    /**
     * @param string $method
     * @param mixed $data
     * @param string $version
     * @param string $id
     * @return mixed
     * @throws Exception
     * @throws ClientExceptionInterface
     */
    public function get(string $method, mixed $data, string $version = '2.0', string $id = ''): ResponseInterface
    {
        if (empty($id)) $id = Number::create(time());

        return $this->client->withConfig($this->get_consul($this->name))->sendRequest(
            $this->requestBody([
                'jsonrpc' => $version,
                'service' => str_starts_with($this->name, '/') ? $this->name : '/' . $this->name,
                'method' => $method,
                'params' => $data,
                'id' => $id
            ])
        );
    }


    /**
     * @param array $data
     * @return mixed
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function batch(array $data): mixed
    {
        return $this->client->withConfig($this->get_consul($this->name))->sendRequest(
            $this->requestBody($data)
        );
    }


    /**
     * @param $service
     * @return array
     * @throws RpcServiceException|\ReflectionException
     * @throws Exception
     */
    #[ArrayShape(['Address' => "mixed", 'Port' => "mixed"])]
    private function get_consul($service): array
    {
        if (empty($service)) {
            throw new RpcServiceException('You need set rpc service name if used.');
        }
        $sf = $this->manager->getServices($service);
        if (empty($sf) || !is_array($sf)) {
            throw new RpcServiceException('You need set rpc service name if used.');
        }
        return $this->_loadRand($sf);
    }


    /**
     * @param $services
     * @return array
     */
    #[ArrayShape(['Address' => "mixed", 'Port' => "mixed"])]
    private function _loadRand($services): array
    {
        $array = [];
        foreach ($services as $value) {
            $value['Weight'] = $value['Weights']['Passing'];
            $array[] = $value;
        }
        if (count($array) < 2) {
            $luck = $array[0];
        } else {
            $luck = Luckdraw::luck($array, 'Weight');
        }
        return [
            'Address' => $luck['TaggedAddresses']['wan_ipv4']['Address'],
            'Port' => $luck['TaggedAddresses']['wan_ipv4']['Port']
        ];
    }

}
