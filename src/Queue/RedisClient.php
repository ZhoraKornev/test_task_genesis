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
     * @return void
     * @throws RedisNotConnectedException
     * @throws \App\Exceptions\EnvHelperInitializationException
     */
    public function init(): void
    {
        //TODO maybe remove this to parameters
        $envHelper = new EnvHelper();
        $envHelper->init();

        $this->redis->connect($envHelper->get('REDIS_HOST'));
        $this->redis->auth($envHelper->get('REDIS_PASSWORD'));
        if (!$this->redis->isConnected()){
            throw new RedisNotConnectedException();
        }
    }

    /**
     * @return Redis
     */
    public function getRedis(): Redis
    {
        return $this->redis;
    }
}
