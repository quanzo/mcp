<?php

/**
 * Точка входа MCP сервера (stdio)
 *
 * Запускает стандартный MCP поверх StdioTransport.
 * Логи — только в файл (не в STDOUT).
 *
 * Пример:
 *   php bin/mcp_server.php
 *
 * Конфиг агента:
 *   { "command": "php", "args": ["/path/to/bin/mcp_server.php"] }
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/vendor/autoload.php';

use quanzo\mcp\classes\McpServerFactory;
use quanzo\mcp\classes\log\FileLogger;
use quanzo\mcp\classes\transport\StdioTransport;

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    fwrite(STDERR, "Требуется PHP 8.1 или выше. Текущая версия: " . PHP_VERSION . "\n");
    exit(1);
}

foreach (['logs', 'config', 'data'] as $dir) {
    $path = $projectRoot . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

$logger = new FileLogger(
    $projectRoot . '/logs/mcp-server.log',
    \Psr\Log\LogLevel::INFO
);

$configFile = $projectRoot . '/config/server.json';
if (!file_exists($configFile)) {
    $defaultConfig = [
        'server' => [
            'name' => 'quanzo-mcp',
            'version' => '1.0.0',
        ],
    ];
    file_put_contents(
        $configFile,
        json_encode($defaultConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

try {
    $mcpServer = McpServerFactory::createDefault($projectRoot, $logger);
    $transport = new StdioTransport($mcpServer, $logger);
    $transport->run();
} catch (\Throwable $e) {
    $logger->error('Ошибка при запуске сервера', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . "\n");
    exit(1);
}
