<?php

namespace App\Queue;

use App\EnvHelper;
use App\Exceptions\RedisNotConnectedException;
use Redis;

class RedisClient
{
    private Redis $redis;

    public function __construct()
    {
        $this->redis = new Redis();
    }

    /**
     * @param string|null $host
     * @param string|null $securityConnection
     * @return void
     * @throws RedisNotConnectedException
     * @throws \App\Exceptions\EnvHelperInitializationException
     */
    public function initConnection(?string $host = null,?string $securityConnection = null): void
    {
        //TODO maybe remove this to parameters
        $envHelper = new EnvHelper();
        $envHelper->init();

        if (!$host) {
            $host = $envHelper->get('REDIS_HOST');
        }
        if (!$securityConnection) {
            $securityConnection = $envHelper->get('REDIS_PASSWORD');
        }
        $this->redis->connect($host);
        $this->redis->auth($securityConnection);
        if (!$this->redis->isConnected()){
            throw new RedisNotConnectedException();
        }
    }


    /**
     * @return array<string>
     */
    public function listQueues(string $nameSpace): array
    {
        return $this->redis->sMembers($nameSpace);
    }

    /**
     * @return Redis
     */
    public function getRedis(): Redis
    {
        return $this->redis;
    }
}
