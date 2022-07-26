<?php

declare(strict_types=1);

namespace App;

interface WorkerSleepProviderInterface
{

    /**
     * Return the number of seconds that the worker should sleep for before grabbing the next message.
     * Returning null or a value less than zero will cause the worker to exit.
     *
     * Note: this method is called _before_ the receiveMessage method is called.
     *
     * @return positive-int|null
     */
    public function getSleep(): ?int;
}
