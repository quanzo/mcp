<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use quanzo\mcp\classes\McpServer;
use quanzo\mcp\classes\dto\mcp\ServerInfo;
use quanzo\mcp\classes\transport\HttpTransportResult;
use quanzo\mcp\classes\transport\StreamableHttpTransport;

/**
 * Класс HeadersTest
 *
 * Проверяет формирование HTTP-заголовков Streamable HTTP транспорта.
 */
class HeadersTest extends TestCase
{
    /**
     * Тест: CORS и Content-Type выставляются на JSON-ответе
     *
     * @return void
     */
    public function testJsonResultIncludesCorsAndContentType(): void
    {
        $result = HttpTransportResult::json(200, ['ok' => true], [
            'Access-Control-Allow-Origin' => '*',
        ]);

        $headers = $result->getHeaders();
        self::assertSame('application/json', $headers['Content-Type']);
        self::assertSame('*', $headers['Access-Control-Allow-Origin']);
    }

    /**
     * Тест: health endpoint доступен вне /mcp
     *
     * @return void
     */
    public function testHealthEndpoint(): void
    {
        $server = new McpServer(new ServerInfo('t', '1'), new NullLogger());
        $transport = new StreamableHttpTransport($server);
        $result = $transport->handleHttpRequest('GET', '/health', [], '');

        self::assertSame(200, $result->getStatusCode());
        self::assertStringContainsString('streamable-http', $result->getBody());
    }
}
