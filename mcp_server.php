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
use app\modules\neuron\mcp\commands\MyCustomCommand;
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
    $server->registerCommand(new MyCustomCommand());

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
    
    // Загрузка или создание конфигурационного файла
    $configFile = __DIR__ . '/config/server.json';
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
    $logger->info('Логи записываются в ' . __DIR__ . '/logs/mcp-server.log');
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