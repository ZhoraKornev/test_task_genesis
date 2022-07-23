<?php

namespace App\Queue;

class DeadQueue extends QueueRedis
{
    public function restoreMessages(string $queue)
    {
        self::moveMessages(
            $queue,
            self::getDeadQueueName($queue),
            $queue
        );
    }

    public function removeMessagesFromQueue(string $queue): bool
    {
        return parent::removeMessages(
            $queue, self::getDeadQueueName($queue)
        );
    }

    public function countMessagesInQueue(string $queue): int
    {
        return parent::countMessages(
            $queue, self::getDeadQueueName($queue)
        );
    }


    public function getMessagesFromQueue(string $queue, int $start, int $end): array
    {
        return parent::getMessages(
            $queue, self::getDeadQueueName($queue), $start, $end
        );
    }
}
