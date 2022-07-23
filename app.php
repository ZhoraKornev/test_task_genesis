<?php
require __DIR__ . '/src/EnvHelper.php';

use App\EnvHelper;
use App\Queue\QueueRedis;

try {
    $publisher = new \App\Queue\Publisher();
    $publisher->pushMessage('test-queue',[1,2,3,34]);
    echo PHP_EOL;
} catch (\Throwable $t) {
    echo 'Error: ' . $t->getMessage();
    echo PHP_EOL;
}
