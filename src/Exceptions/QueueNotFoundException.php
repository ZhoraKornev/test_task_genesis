<?php

namespace App\Exceptions;

class QueueNotFoundException extends \Exception
{
    protected $message = 'Queue not found.';
}