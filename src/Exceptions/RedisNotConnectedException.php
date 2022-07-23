<?php

namespace App\Exceptions;

class RedisNotConnectedException extends \Exception
{
    protected $message = 'Redis connection FAIL';
}