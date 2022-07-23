<?php

namespace App\Exceptions;

class EnvHelperInitializationException extends \Exception
{
    protected $message = 'Env Helper can not load file with env';
}