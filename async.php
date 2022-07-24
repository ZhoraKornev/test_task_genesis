<?php
/**
 * @var AndrewBreksa\RSMQ\RSMQClientInterface $rsmq
 */

use AndrewBreksa\RSMQ\ExecutorInterface;
use AndrewBreksa\RSMQ\Message;
use AndrewBreksa\RSMQ\QueueWorker;
use AndrewBreksa\RSMQ\WorkerSleepProvider;

$executor = new class() implements ExecutorInterface{
    public function __invoke(Message $message) : bool {
        //@todo: do some work, true will ack/delete the message, false will allow the queue's config to "re-publish"
        return true;
    }
};

$sleepProvider = new class() implements WorkerSleepProvider{
    public function getSleep() : ?int {
        /**
         * This allows you to return null to stop the worker, which can be used with something like redis to mark.
         *
         * Note that this method is called _before_ we poll for a message, and therefore if it returns null we'll eject
         * before we process a message.
         */
        return 1;
    }
};

$worker = new QueueWorker($rsmq, $executor, $sleepProvider, 'test_queue');
$worker->work(); // here we can optionally pass true to only process one message