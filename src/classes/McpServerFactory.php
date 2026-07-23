<?php

declare(strict_types=1);

namespace quanzo\mcp\classes;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use quanzo\mcp\classes\dto\mcp\ServerInfo;
use quanzo\mcp\classes\resources\FileResource;
use quanzo\mcp\commands\CalculateCommand;
use quanzo\mcp\commands\EchoCommand;
use quanzo\mcp\commands\OllamaSearchCommand;
use quanzo\mcp\commands\RuWikiSearchCommand;
use quanzo\mcp\commands\UniSearchCommand;
use quanzo\mcp\commands\UserCommand;
use quanzo\mcp\commands\WikiSearchCommand;

/**
 * Класс McpServerFactory
 *
 * Собирает настроенный McpServer с демо-инструментами и ресурсами проекта.
 *
 * Пример использования:
 *   $server = McpServerFactory::createDefault($projectRoot, $logger);
 */
class McpServerFactory
{
    /**
     * Создаёт сервер с демо-командами и ресурсами
     *
     * @param string $projectRoot Корень проекта
     * @param LoggerInterface|null $logger Логгер
     * @param string|null $name Имя сервера
     * @param string|null $version Версия сервера
     *
     * @return McpServer
     */
    public static function createDefault(
        string $projectRoot,
        ?LoggerInterface $logger = null,
        ?string $name = null,
        ?string $version = null
    ): McpServer {
        $logger = $logger ?? new NullLogger();
        $configFile = $projectRoot . '/config/server.json';
        $serverName = $name ?? 'quanzo-mcp';
        $serverVersion = $version ?? '1.0.0';

        if (is_file($configFile)) {
            $raw = file_get_contents($configFile);
            if ($raw !== false) {
                $config = json_decode($raw, true);
                if (is_array($config) && isset($config['server']) && is_array($config['server'])) {
                    $serverName = is_string($config['server']['name'] ?? null)
                        ? $config['server']['name']
                        : $serverName;
                    $serverVersion = is_string($config['server']['version'] ?? null)
                        ? $config['server']['version']
                        : $serverVersion;
                }
            }
        }

        $server = new McpServer(new ServerInfo($serverName, $serverVersion), $logger);
        $server->registerCommand(new EchoCommand());
        $server->registerCommand(new CalculateCommand());
        $server->registerCommand(new UserCommand());
        $server->registerCommand(new WikiSearchCommand());
        $server->registerCommand(new RuWikiSearchCommand());
        $server->registerCommand(new UniSearchCommand());
        $server->registerCommand(new OllamaSearchCommand());

        $server->registerResource(
            new FileResource(
                'file://logs/mcp-server.log',
                'text/plain',
                $projectRoot . '/logs',
                'mcp_server_log',
                'Лог-файл MCP сервера'
            )
        );

        $server->registerResource(
            new FileResource(
                'config://server.json',
                'application/json',
                $projectRoot . '/config',
                'server_config',
                'Конфигурация сервера'
            )
        );

        return $server;
    }
}
