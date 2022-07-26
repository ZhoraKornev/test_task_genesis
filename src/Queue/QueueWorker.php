<?php

declare(strict_types=1);

namespace App\Queue;

use App\ExecutorInterface;
use App\WorkerSleepProviderInterface;

//TODO rewrite this to Predis subscriber https://github.com/predis/predis/blob/v0.8/examples/PubSubContext.php
//https://redis.io/docs/manual/pubsub/

class QueueWorker
{
    protected QueueRedis $queueRedis;
    protected ExecutorInterface $executor;
    protected WorkerSleepProviderInterface $sleepProvider;
    protected string $queue;
    protected int $received = 0;
    protected int $failed = 0;
    protected int $successful = 0;

    public function __construct(
        QueueRedis $rsmq,
        ExecutorInterface $executor,
        WorkerSleepProviderInterface $sleepProvider,
        string $queue
    )
    {
        $this->queueRedis = $rsmq;
        $this->executor = $executor;
        $this->sleepProvider = $sleepProvider;
        $this->queue = $queue;
    }

    public function work(bool $processOne = false): void
    {
        while (true) {
            $sleep = $this->sleepProvider->getSleep();
            if ($sleep === null || $sleep < 0) {
                break;
            }
            $message = $this->queueRedis->receiveMessage($this->queue);
            if (!($message instanceof Message)) {
                sleep($sleep);
                continue;
            }
            $this->received++;
            $result = $this->executor->__invoke($message);
            if ($result === true) {
                $this->successful++;
                $this->queueRedis->deleteMessage($this->queue, $message->getId());
            } else {
                $this->failed++;
            }
            if ($processOne && $this->getProcessedCount() === 1) {
                break;
            }
        }
    }

    public function getProcessedCount(): int
    {
        return $this->successful + $this->failed;
    }

    public function getReceived(): int
    {
        return $this->received;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function getSuccessful(): int
    {
        return $this->successful;
    }
}
