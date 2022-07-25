<?php

use App\Queue\QueueRedis;
use PHPUnit\Framework\TestCase;

class QueueRedisTest extends TestCase
{
    private QueueRedis $testingService;

    public function setUp(): void
    {
        $this->testingService = new QueueRedis();
        $this->testingService->init();
    }

    public function testScriptsShouldInitialized(): void
    {
        $reflection = new ReflectionClass($this->testingService);

        $recvMsgRef = $reflection->getProperty('receiveMessageSha1');
        $recvMsgRef->setAccessible(true);

        $this->assertSame(40, strlen($recvMsgRef->getValue($this->testingService)));

        $popMsgRef = $reflection->getProperty('popMessageSha1');
        $popMsgRef->setAccessible(true);

        $this->assertSame(40, strlen($popMsgRef->getValue($this->testingService)));
    }

    /**
     * @after testCreateQueueWithSmallMaxSize
     */
    public function testCreateQueueAfterDeleting(): void
    {
        $this->testingService->deleteQueue('foo');
        $this->assertTrue($this->testingService->createQueue('foo'));
    }

    public function testCreateQueueWithSmallMaxSize(): void
    {
        $this->expectException(\App\Exceptions\QueueValidationException::class);
        $this->expectExceptionMessage('Maximum message size must be between');
        $this->testingService->createQueue('foo', 30, 0, 1023);
    }
}