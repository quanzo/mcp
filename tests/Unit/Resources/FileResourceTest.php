<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use quanzo\mcp\classes\McpServer;
use quanzo\mcp\classes\dto\mcp\ServerInfo;
use quanzo\mcp\classes\resources\FileResource;

/**
 * Класс FileResourceTest
 *
 * Тестирует FileResource: matchesUri, чтение файла, ошибку missing file
 * и реакцию McpServer (JSON-RPC error, без падения).
 */
class FileResourceTest extends TestCase
{
    /**
     * Тест: matchesUri — exact, wildcard, mismatch
     *
     * @return void
     */
    public function testMatchesUriExactWildcardAndMismatch(): void
    {
        $exact = new FileResource('file://logs/a.log', 'text/plain', '/tmp', 'a', null);
        self::assertTrue($exact->matchesUri('file://logs/a.log'));
        self::assertFalse($exact->matchesUri('file://logs/b.log'));

        $wild = new FileResource('file://logs/*', 'text/plain', '/tmp', 'logs', null);
        self::assertTrue($wild->matchesUri('file://logs/x.txt'));
        self::assertFalse($wild->matchesUri('file://other/x.txt'));
    }

    /**
     * Тест: отсутствующий файл → RuntimeException на уровне ресурса
     *
     * @return void
     */
    public function testMissingFileThrowsRuntimeException(): void
    {
        $dir = sys_get_temp_dir() . '/mcp_fr_' . uniqid('', true);
        mkdir($dir);
        $resource = new FileResource('file://missing.txt', 'text/plain', $dir, 'm', null);

        $this->expectException(\RuntimeException::class);
        try {
            $resource->getContent('file://missing.txt');
        } finally {
            @rmdir($dir);
        }
    }

    /**
     * Тест: успешное чтение существующего файла
     *
     * @return void
     */
    public function testGetContentReadsExistingFile(): void
    {
        $dir = sys_get_temp_dir() . '/mcp_fr_' . uniqid('', true);
        mkdir($dir);
        file_put_contents($dir . '/data.txt', 'payload');

        $resource = new FileResource('file://data.txt', 'text/plain', $dir, 'data', 'desc');
        self::assertSame('payload', $resource->getContent('file://data.txt'));
        self::assertSame('data', $resource->getName());
        self::assertSame('desc', $resource->getDescription());

        @unlink($dir . '/data.txt');
        @rmdir($dir);
    }

    /**
     * Тест resilience: missing file через resources/read → JSON-RPC error, не uncaught
     *
     * @return void
     */
    public function testMissingFileViaMcpServerReturnsError(): void
    {
        $dir = sys_get_temp_dir() . '/mcp_fr_' . uniqid('', true);
        mkdir($dir);

        $server = new McpServer(new ServerInfo('t', '1'), new NullLogger());
        $server->registerResource(new FileResource('file://gone.txt', 'text/plain', $dir, 'gone', null));

        $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => [],
                'clientInfo' => ['name' => 'c', 'version' => '1'],
            ],
        ]);
        $server->handleMessage(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'resources/read',
            'params' => ['uri' => 'file://gone.txt'],
        ]);

        self::assertSame(-32603, $response['error']['code']);
        @rmdir($dir);
    }
}
