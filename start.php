<?php

require __DIR__ . '/src/Queue/QueueRedis.php';
require __DIR__ . '/src/Queue/EnvDefaultsQueue.php';
require __DIR__ . '/src/Queue/RedisClient.php';
require __DIR__ . '/src/Queue/QueueValidator.php';
require __DIR__ . '/src/Queue/ValueFormatHelper.php';
require __DIR__ . '/src/EnvHelper.php';
require __DIR__ . '/src/Queue/QueueAttributesModel.php';
require __DIR__ . '/src/Queue/Message.php';
require __DIR__ . '/src/Queue/QueueWorker.php';
require __DIR__ . '/src/Exceptions/QueueValidationException.php';
require __DIR__ . '/src/Exceptions/QueueAlreadyExistsException.php';
require __DIR__ . '/src/Exceptions/QueueNotFoundException.php';
require __DIR__ . '/src/ExecutorInterface.php';
require __DIR__ . '/src/WorkerSleepProviderInterface.php';

use App\Exceptions\QueueAlreadyExistsException;
use App\Exceptions\QueueValidationException;
use App\Queue\QueueRedis;

$queueTestName = 'test_queue';
$qr = new QueueRedis();
$qr->init();
try {
    $qr->createQueue($queueTestName);
} catch (QueueValidationException $e) {
    echo $e->getMessage();
    echo PHP_EOL;
} catch (QueueAlreadyExistsException $e) {
    echo $e->getMessage();
    echo PHP_EOL;
}
print_r($qr->listQueues());

$attributes =  $qr->getQueueAttributes($queueTestName);
echo "visibility timeout: ", $attributes->getVt(), "\n";
echo "delay for new messages: ", $attributes->getDelay(), "\n";
echo "max size in bytes: ", $attributes->getMaxSize(), "\n";
echo "total received messages: ", $attributes->getTotalReceived(), "\n";
echo "total sent messages: ", $attributes->getTotalSent(), "\n";
echo "created: ", $attributes->getCreated(), "\n";
echo "last modified: ", $attributes->getModified(), "\n";
echo "current n of messages: ", $attributes->getMessageCount(), "\n";
echo "hidden messages: ", $attributes->getHiddenMessageCount(), "\n";


echo "Enter queue message ";
$queueTextMessage = fgets(fopen("php://stdin", "r"));
echo PHP_EOL;

echo "Enter queue delay on seconds ";
echo PHP_EOL;
$queueDelay = (int)fgets(fopen("php://stdin", "r"));

$id = $qr->sendMessage($queueTestName, $queueTextMessage, $queueDelay);
echo "Send msg = $queueTextMessage in queue with name = $queueTestName  and delay = $queueDelay Message id = $id";echo PHP_EOL;

$message = $qr->popMessage($queueTestName);
$executor = new class() implements \App\ExecutorInterface {
    public function __invoke(\App\Queue\Message $message) : bool {
        //@todo: do some work, true will ack/delete the message, false will allow the queue's config to "re-publish"
        return true;
    }
};

$sleepProvider = new class() implements \App\WorkerSleepProviderInterface {
    public function getSleep() : ?int {
        return 1;
    }
};

$worker = new \App\Queue\QueueWorker($qr, $executor, $sleepProvider, $queueTestName);
$worker->work();
