<?php

namespace App\Queue;

class Publisher extends \App\Queue\QueueRedis
{
    public function pushMessage(string $queue, array $message)
    {
        $this->redisClients[$queue]->getRedis()->lPush($queue, json_encode($message));
    }
}
