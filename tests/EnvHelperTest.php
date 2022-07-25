<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\EnvHelper;

class EnvHelperTest extends TestCase
{
    public function testParseOk(): void
    {
        $filename = __DIR__ . "/.env";
        $envHelper = new EnvHelper($filename);
        $envHelper->init();
        $this->assertSame('test_value', $envHelper->get('TEST_KEY'));
    }

    public function testSetArray(): void
    {
        $this->assertTrue(true);
    }
}