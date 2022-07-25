<?php

namespace App\Queue;

use App\Exceptions\QueueValidationException;

class QueueValidator
{
    /**
     * @param array $params
     * @return void
     * @throws QueueValidationException
     */
    public function validate(array $params): void
    {
        if (isset($params['queue']) && !preg_match('/^([a-zA-Z0-9_-]){1,160}$/', $params['queue'])) {
            throw new QueueValidationException('Invalid queue name');
        }

        if (isset($params['id']) && !preg_match('/^([a-zA-Z0-9:]){32}$/', $params['id'])) {
            throw new QueueValidationException('Invalid message id');
        }

        if (isset($params['vt']) && ($params['vt'] < 0 || $params['vt'] > EnvDefaultsQueue::MAX_DELAY)) {
            throw new QueueValidationException('Visibility time must be between 0 and ' . EnvDefaultsQueue::MAX_DELAY);
        }

        if (isset($params['delay']) && ($params['delay'] < 0 || $params['delay'] > EnvDefaultsQueue::MAX_DELAY)) {
            throw new QueueValidationException('Delay must be between 0 and ' . EnvDefaultsQueue::MAX_DELAY);
        }

        if (isset($params['maxsize']) &&
            ($params['maxsize'] < EnvDefaultsQueue::MIN_MESSAGE_SIZE || $params['maxsize'] > EnvDefaultsQueue::MAX_PAYLOAD_SIZE)) {
            $message = "Maximum message size must be between %d and %d";
            throw new QueueValidationException(sprintf($message, EnvDefaultsQueue::MIN_MESSAGE_SIZE, EnvDefaultsQueue::MAX_PAYLOAD_SIZE));
        }
    }
}