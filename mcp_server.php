<?php
/**
 * Скрипт запуска MCP сервера (mcp_server.php)
 * 
 * Демонстрирует базовое использование MCP сервера:
 * 1. Создание логгера
 * 2. Инициализация сервера с авторизацией
 * 3. Регистрация команд и ресурсов
 * 4. Запуск сервера в stdio режиме
 */

require_once __DIR__ . '/vendor/autoload.php';

use app\modules\neuron\mcp\Server;
use app\modules\neuron\mcp\log\FileLogger;
use app\modules\neuron\mcp\commands\EchoCommand;
use app\modules\neuron\mcp\commands\CalculateCommand;
use app\modules\neuron\mcp\commands\UserCommand;
use app\modules\neuron\mcp\resources\FileResource;

// Проверка версии PHP
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    die("Требуется PHP 8.1 или выше. Текущая версия: " . PHP_VERSION . "\n");
}

// Создаем директории для логов и конфигурации
$directories = ['logs', 'config', 'data'];
foreach ($directories as $dir) {
    if (!is_dir(__DIR__ . '/' . $dir)) {
        mkdir(__DIR__ . '/' . $dir, 0755, true);
    }
}

// Настройка логгера
$logger = new FileLogger(
    __DIR__ . '/logs/mcp-server.log',
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
    
    // Регистрация ресурсов
    $server->registerResource(
        new FileResource(
            'file://logs/mcp-server.log',
            'text/plain',
            __DIR__ . '/logs'
        )
    );
    
    $server->registerResource(
        new FileResource(
            'config://server.json',
            'application/json',
            __DIR__ . '/config'
        )
    );
    
    // Создание конфигурационного файла, если не существует
    $configFile = __DIR__ . '/config/server.json';
    if (!file_exists($configFile)) {
        file_put_contents($configFile, json_encode([
            'server' => [
                'name' => 'MCP PHP Server',
                'version' => '1.0.0',
                'started_at' => date('c')
            ],
            'commands' => ['echo', 'calculate', 'user.create'],
            'auth_required' => true
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**/
    $logger->info("MCP сервер запущен. Auth key: $authKey");
    $logger->info("Логи будут записываться в: " . __DIR__ . "/logs/mcp-server.log");
    $logger->info("Ожидание запросов через stdio...");
    //*/
    
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