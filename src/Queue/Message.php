<?php

declare(strict_types=1);

namespace App\Queue;

class Message
{
    protected string $id;
    protected string $message;
    protected int $receiveCount;
    protected int $firstReceived;
    protected float $sent;

    public function __construct(string $id, string $message, int $receiveCount, int $firstReceived, float $sent)
    {
        $this->id = $id;
        $this->message = $message;
        $this->receiveCount = $receiveCount;
        $this->firstReceived = $firstReceived;
        $this->sent = $sent;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getReceiveCount(): int
    {
        return $this->receiveCount;
    }

    public function getFirstReceived(): int
    {
        return $this->firstReceived;
    }

    public function getSent(): float
    {
        return $this->sent;
    }
}