<?php
/**
 * Скрипт запуска MCP сервера (bin/mcp_server.php)
 *
 * Демонстрирует базовое использование MCP сервера:
 * 1. Создание логгера
 * 2. Инициализация сервера с авторизацией
 * 3. Регистрация команд и ресурсов
 * 4. Запуск сервера в stdio режиме
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/vendor/autoload.php';

use quanzo\mcp\classes\Server;
use quanzo\mcp\classes\log\FileLogger;
use quanzo\mcp\classes\commands\EchoCommand;
use quanzo\mcp\classes\commands\CalculateCommand;
use quanzo\mcp\classes\commands\UserCommand;
use quanzo\mcp\classes\commands\MyCustomCommand;
use quanzo\mcp\classes\resources\FileResource;

// Проверка версии PHP
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    die("Требуется PHP 8.1 или выше. Текущая версия: " . PHP_VERSION . "\n");
}

// Создаем директории для логов и конфигурации
$directories = ['logs', 'config', 'data'];
foreach ($directories as $dir) {
    if (!is_dir($projectRoot . '/' . $dir)) {
        mkdir($projectRoot . '/' . $dir, 0755, true);
    }
}

// Настройка логгера
$logger = new FileLogger(
    $projectRoot . '/logs/mcp-server.log',
    \Psr\Log\LogLevel::INFO
);

// Ключ авторизации (можно получить из переменных окружения)
$authKey = getenv('MCP_AUTH_KEY') ?: 'default-secret-key-123';

// Создание сервера
$server = new Server($authKey, $logger);

try {
    // Регистрация команд
    $server->registerCommand(new EchoCommand());
    $server->registerCommand(new CalculateCommand());
    $server->registerCommand(new UserCommand());
    $server->registerCommand(new MyCustomCommand());

    // Регистрация ресурсов
    $server->registerResource(
        new FileResource(
            'file://logs/mcp-server.log',
            'text/plain',
            $projectRoot . '/logs'
        )
    );

    $server->registerResource(
        new FileResource(
            'config://server.json',
            'application/json',
            $projectRoot . '/config'
        )
    );

    // Загрузка или создание конфигурационного файла
    $configFile = $projectRoot . '/config/server.json';
    $serverConfig = null;
    if (file_exists($configFile)) {
        $serverConfig = json_decode(file_get_contents($configFile), true);
    }
    if (!file_exists($configFile)) {
        $defaultConfig = [
            'server' => [
                'name' => 'MCP PHP Server',
                'version' => '1.0.0',
                'started_at' => date('c')
            ],
            'commands' => ['echo', 'calculate', 'user.create', 'my.command'],
            'auth_required' => true
        ];
        file_put_contents($configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $serverConfig = $defaultConfig;
    }

    $serverName = $serverConfig['server']['name'] ?? 'MCP PHP Server';
    $serverVersion = $serverConfig['server']['version'] ?? '1.0.0';
    $logger->info('MCP сервер запущен', [
        'name' => $serverName,
        'version' => $serverVersion,
        'auth_key_set' => true
    ]);
    $logger->info('Логи записываются в ' . $projectRoot . '/logs/mcp-server.log');
    $logger->info('Ожидание запросов через stdio...');

    // Запуск сервера
    $server->run();
} catch (\Exception $e) {
    $logger->error('Ошибка при запуске сервера', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    echo "Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

