<?php

require __DIR__ . '/src/Queue/QueueRedis.php';
require __DIR__ . '/src/Queue/EnvDefaultsQueue.php';
require __DIR__ . '/src/Queue/RedisClient.php';
require __DIR__ . '/src/Queue/QueueValidator.php';
require __DIR__ . '/src/Queue/ValueFormatHelper.php';
require __DIR__ . '/src/EnvHelper.php';
require __DIR__ . '/src/Queue/QueueAttributesModel.php';

use App\Queue\QueueRedis;

$qr = new QueueRedis();
$qr->init();
$qr->deleteQueue('test_queue');
$qr->createQueue('test_queue');
print_r($qr->listQueues());

$attributes =  $qr->getQueueAttributes('test_queue');
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

echo "Send msg = $queueTextMessage in queue with name = ''  and delay = $queueDelay ";echo PHP_EOL;