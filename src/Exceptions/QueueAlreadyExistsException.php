<?php

namespace App\Exceptions;

class QueueAlreadyExistsException extends \Exception
{
    protected $message = 'Queue already exists.';
}