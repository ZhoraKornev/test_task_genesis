<?php

namespace App\Queue;

use App\Exceptions\QueueWithoutConnectionException;
use App\Exceptions\RedisNotConnectedException;

abstract class QueueRedis
{
    private const QUEUE_NAME_DEFAULT = 'default';

    /** @var RedisClient[]  */
    protected array $redisClients;

    public function __construct()
    {
        $this->redisClients = array();
    }

    /**
     * @param string $queueName
     * @return void
     * @throws RedisNotConnectedException
     * @throws \App\Exceptions\EnvHelperInitializationException
     */
    public function initQueue(string $queueName): void
    {
        if (!empty($this->redisClients[$queueName])){
            $redisClient = new RedisClient();
            $redisClient->init();
            $this->redisClients[$queueName] = $redisClient;

        }
    }

    public function initDefaultQueue(): void
    {
        if (!empty($this->redisClients[QueueRedis::QUEUE_NAME_DEFAULT])){
            $redisClient = new RedisClient();
            $redisClient->init();
            $this->redisClients[QueueRedis::QUEUE_NAME_DEFAULT] = $redisClient;

        }
    }

    protected function getProcessingQueueName(string $queue, string $worker): string
    {
        return "{$queue}-worker-{$worker}";
    }

    protected function getDeadQueueName(string $queue): string
    {
        return "{$queue}-dead";
    }

    protected function moveMessages(string $queue, string $source, string $destination): void
    {
        do {
            $redis = $this->redisClients[$queue]->getRedis();
            $value = $redis->rpoplpush($source, $destination);
        } while ($value !== false);
    }

    protected function removeMessages(string $queue, string $key): bool
    {
        return (bool) $this->redisClients[$queue]->getRedis()->del($key);
    }

    protected function countMessages(string $queue, string $key): int
    {
        return (int) $this->redisClients[$queue]->getRedis()->lLen($key);
    }

    protected function getMessages(string $queue, string $key, int $start, int $end): array
    {
        return $this->redisClients[$queue]->getRedis()->lRange(
            $key, $start, $end
        );
    }
}
