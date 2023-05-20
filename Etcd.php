<?php

use Etcd\Client;
use GuzzleHttp\Exception\BadResponseException;
use Kiri\Abstracts\Component;
use Kiri\Core\Str;


/**
 *
 */
class Etcd extends Component
{


    private Client $client;


    private bool $isEnd = false;


    private array $config = [];


    private array|BadResponseException $grant;


    /**
     * @return void
     * @throws Exception
     */
    public function init(): void
    {
        $this->client = new Client('47.92.194.207:' . 2379, 'v3');
        $this->grant = $this->client->grant(60);
        if ($this->grant instanceof BadResponseException) {
            throw new Exception($this->grant->getMessage());
        }

        $key = 'center.service.' . gethostbyname(gethostname());
        pcntl_signal(SIGINT, function () use ($key) {
            $this->isEnd = true;
            $this->client->del($key);
        });
        $this->client->put($key, json_encode([
            'address' => gethostbyname(gethostname()) . ':10240',
            'nodeId' => Str::rand(32)
        ]), ['lease' => (int)$this->grant["ID"]]);
    }


    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $this->config[$key] ?? null;
    }


    /**
     * @param string $key
     * @param mixed $value
     * @return void
     * @throws Exception
     */
    public function put(string $key, mixed $value): void
    {
        $result = $this->client->put($key, $value);
        if ($result instanceof BadResponseException) {
            throw new Exception($result->getMessage());
        }
        $this->config[$key] = $value;
    }


    /**
     * @return void
     */
    public function waite(): void
    {
        while ($this->isEnd == false) {
            $this->client->keepAlive((int)$this->grant["ID"]);
            sleep(1);
        }
    }


}