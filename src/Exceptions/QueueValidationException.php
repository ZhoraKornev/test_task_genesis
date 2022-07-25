<?php

namespace App\Exceptions;

class QueueValidationException extends \Exception
{
    protected $message = 'Invalid parameter send to queue';
}