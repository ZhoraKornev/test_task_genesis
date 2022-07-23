<?php

try {
    echo 'Current PHP version: ' . phpversion();
    echo PHP_EOL;

    $redis = new Redis();
    //TODO remove this with ENV
    $redis->connect('queue-redis');
    $redis->auth('q1w2e3r4');
    if ($redis->isConnected()){
        echo 'Database connected successfully';
    }
    echo PHP_EOL;

} catch (\Throwable $t) {
    echo 'Error: ' . $t->getMessage();
    echo PHP_EOL;
}
