<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use app\modules\neuron\mcp\client\MCPClient;

/**
 * Класс MCPClientServerIntegrationTest
 *
 * Проводит интеграционные тесты взаимодействия MCPClient с реальным серверным скриптом.
 * Используются только безопасные bash-команды (запуск php-процесса).
 */
class MCPClientServerIntegrationTest extends TestCase
{
    /**
     * Тестирует базовый сценарий: поднятие сервера и получение списка команд
     *
     * @return void
     */
    public function testListCommandsFromRealServer(): void
    {
        $serverScript = __DIR__ . '/../../mcp_server.php';

        if (!file_exists($serverScript)) {
            $this->markTestSkipped('Скрипт сервера mcp_server.php не найден для интеграционного теста');
        }

        $client = new MCPClient($serverScript);

        try {
            $commands = $client->listCommands();

            self::assertIsArray($commands);
        } finally {
            $client->close();
        }
    }
}
