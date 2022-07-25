<?php

namespace App\Queue;

class EnvDefaultsQueue
{

    public const MAX_PAYLOAD_SIZE = 65536;
    public const QUEUE_NAME_SPACE_DEFAULT = 'default_ns';
    public const QUEUE_NAME_DEFAULT = 'default';
    public const MAX_DELAY = 9999999;
    public const MIN_MESSAGE_SIZE = 1024;
    public const STANDART_ID_LENGTH = 22;
    public const BASE_CONVERT_FROM = 10;
    public const BASE_CONVERT_TO = 36;
}