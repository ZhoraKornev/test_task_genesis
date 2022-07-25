<?php

namespace App\Exceptions;

class MessageToLongException extends \Exception
{
    protected $message = 'Message too long';
}