<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use quanzo\mcp\classes\MCPHttpServerAmp;

class HeadersTest extends TestCase
{
    public function testAmpHeadersCanBeOverridden(): void
    {
        $server = new MCPHttpServerAmp(__FILE__, dirname(__DIR__, 3) . '/src/templates', '127.0.0.1', 8080, 'key');
        $server->addHeader('content-type', 'application/problem+json');

        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('getHeaders');

        /** @var array<string, string> $headers */
        $headers = $method->invoke($server);

        self::assertArrayHasKey('content-type', $headers);
        self::assertSame('application/problem+json', $headers['content-type']);
    }
}
