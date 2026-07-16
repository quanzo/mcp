<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use quanzo\mcp\classes\client\MCPClient;

/**
 * Класс MCPClientServerIntegrationTest
 *
 * Интеграционные тесты MCPClient с реальным bin/mcp_server.php (stdio).
 * Используются только безопасные команды (запуск php-процесса).
 */
class MCPClientServerIntegrationTest extends TestCase
{
    /**
     * Тест: initialize → tools/list → tools/call → resources/list
     *
     * @return void
     */
    public function testAgentLikeStdioFlow(): void
    {
        $serverScript = __DIR__ . '/../../bin/mcp_server.php';

        if (!file_exists($serverScript)) {
            $this->markTestSkipped('Скрипт сервера mcp_server.php не найден');
        }

        $client = new MCPClient($serverScript);

        try {
            $tools = $client->listTools();
            self::assertNotEmpty($tools);

            $names = array_column($tools, 'name');
            self::assertContains('echo', $names);
            self::assertContains('user_create', $names);

            $call = $client->callTool('echo', ['message' => 'integration']);
            self::assertFalse($call['isError']);
            self::assertStringContainsString('integration', $call['content'][0]['text']);

            $resources = $client->listResources();
            self::assertIsArray($resources);

            $ping = $client->ping();
            self::assertIsArray($ping);
        } finally {
            $client->close();
        }
    }

    /**
     * Тест: неверный tool через клиент бросает исключение
     *
     * @return void
     */
    public function testUnknownToolThrows(): void
    {
        $serverScript = __DIR__ . '/../../bin/mcp_server.php';
        if (!file_exists($serverScript)) {
            $this->markTestSkipped('Скрипт сервера mcp_server.php не найден');
        }

        $client = new MCPClient($serverScript);

        try {
            $this->expectException(\RuntimeException::class);
            $client->callTool('definitely_missing_tool', []);
        } finally {
            $client->close();
        }
    }

    /**
     * Тест resilience: divide-by-zero → isError true, затем успешный echo
     *
     * @return void
     */
    public function testDivideByZeroIsErrorThenRecovery(): void
    {
        $serverScript = __DIR__ . '/../../bin/mcp_server.php';
        if (!file_exists($serverScript)) {
            $this->markTestSkipped('Скрипт сервера mcp_server.php не найден');
        }

        $client = new MCPClient($serverScript);

        try {
            $bad = $client->callTool('calculate', [
                'operation' => 'divide',
                'a' => 10,
                'b' => 0,
            ]);
            self::assertTrue($bad['isError']);

            $ok = $client->callTool('echo', ['message' => 'recovered']);
            self::assertFalse($ok['isError']);
            self::assertStringContainsString('recovered', $ok['content'][0]['text']);
        } finally {
            $client->close();
        }
    }
}
