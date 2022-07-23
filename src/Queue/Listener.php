<?php

namespace App\Queue;

class Listener extends QueueRedis
{
    public function restoreMessagesFromProcessingQueue(string $queue, string $worker)
    {
        $this->moveMessages(
            $queue,
            self::getProcessingQueueName($queue, $worker),
            $queue
        );
    }

    public function processMessages(string $queue, string $worker, int $quantity, callable $callback)
    {
        if ($events = self::getMessagesFromQueue($queue, $worker, $quantity)) {
            try {
                $callback(
                    $quantity === 1 ? $events[0] : $events
                );

                self::removeMessagesFromQueue($queue, $worker, $events);
            } catch (\Throwable $e) {
                self::moveMessagesToDeadQueue($queue, $worker);
            }
        }
    }

    protected  function getMessagesFromQueue(string $queue, string $worker, int $quantity): array
    {
        $clientMulti = $this->redisClients[$queue]->getRedis()->multi();

        for ($i = 0; $i < $quantity; $i++) {
            $clientMulti->brpoplpush(
                $queue,
                self::getProcessingQueueName($queue, $worker),
                0
            );
        }

        return array_filter(
            $clientMulti->exec()
        );
    }

    protected  function removeMessagesFromQueue(string $queue, string $worker, $events)
    {
        $clientMulti = $this->redisClients[$queue]->getRedis()->multi();

        foreach ($events as $value) {
            $clientMulti->lRem(
                self::getProcessingQueueName($queue, $worker),
                $value,
                1
            );
        }

        $clientMulti->exec();
    }

    protected function moveMessagesToDeadQueue(string $queue, string $worker)
    {
        $this->moveMessages(
            $queue,
            self::getProcessingQueueName($queue, $worker),
            self::getDeadQueueName($queue)
        );
    }
}
