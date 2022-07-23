<?php

require __DIR__ . '/src/EnvHelper.php';

use App\EnvHelper;

try {
    echo 'Current PHP version: ' . phpversion();
    echo PHP_EOL;

    $envHelper = new EnvHelper();
    $envHelper->init();
    echo 'ENV vars loaded - OK';
    echo PHP_EOL;
    $redis = new Redis();
    $redis->connect($envHelper->get('REDIS_HOST'));
    $redis->auth($envHelper->get('REDIS_PASSWORD'));
    if ($redis->isConnected()){
        echo 'Redis connected successfully';
    }
    echo PHP_EOL;

} catch (\Throwable $t) {
    echo 'Error: ' . $t->getMessage();
    echo PHP_EOL;
}
