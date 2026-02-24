<?php

require_once __DIR__ . '/vendor/autoload.php';

use app\modules\neuron\mcp\MCPHttpServer;

// Обработка аргументов командной строки
if (php_sapi_name() === 'cli') {
    $port = 8080;
    $authKey = 'default-secret-key-123';
    $host = '0.0.0.0';
    
    // Парсим аргументы командной строки
    $options = getopt('p:h:k:', ['port:', 'host:', 'key:', 'help']);
    
    if (isset($options['help']) || isset($options['h'])) {
        echo "Использование: php http_server.php [опции]\n\n";
        echo "Опции:\n";
        echo "  -p, --port=PORT     Порт для запуска сервера (по умолчанию: 8080)\n";
        echo "  -h, --host=HOST     Хост для запуска сервера (по умолчанию: 0.0.0.0)\n";
        echo "  -k, --key=KEY       Ключ авторизации MCP сервера\n";
        echo "      --help          Показать эту справку\n\n";
        echo "Примеры:\n";
        echo "  php http_server.php\n";
        echo "  php http_server.php -p 9000\n";
        echo "  php http_server.php --port=9000 --host=127.0.0.1\n";
        echo "  php http_server.php -p 8080 -k my-secret-key\n";
        exit(0);
    }
    
    // Получаем порт
    if (isset($options['p'])) {
        $port = (int)$options['p'];
    } elseif (isset($options['port'])) {
        $port = (int)$options['port'];
    } elseif (isset($argv[1]) && is_numeric($argv[1])) {
        $port = (int)$argv[1];
    }
    
    // Получаем хост
    if (isset($options['h'])) {
        $host = $options['h'];
    } elseif (isset($options['host'])) {
        $host = $options['host'];
    } elseif (isset($argv[2]) && !is_numeric($argv[2])) {
        $host = $argv[2];
    }
    
    // Получаем ключ авторизации
    if (isset($options['k'])) {
        $authKey = $options['k'];
    } elseif (isset($options['key'])) {
        $authKey = $options['key'];
    } elseif (isset($argv[3])) {
        $authKey = $argv[3];
    }
    
    // Проверяем порт
    if ($port < 1 || $port > 65535) {
        echo "Ошибка: Порт должен быть в диапазоне 1-65535\n";
        exit(1);
    }
    
    try {
        $server = new MCPHttpServer($port, $authKey, $host);
        $server->run();
    } catch (\Throwable $e) {
        echo "Ошибка запуска сервера: " . $e->getMessage() . "\n";
        echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
        exit(1);
    }
} else {
    // Веб-сервер режим (Apache/Nginx)
    // Получаем настройки из переменных окружения или используем значения по умолчанию
    $port = (int)($_ENV['MCP_HTTP_PORT'] ?? 8080);
    $authKey = $_ENV['MCP_AUTH_KEY'] ?? 'default-secret-key-123';
    $host = $_ENV['MCP_HTTP_HOST'] ?? '0.0.0.0';
    
    try {
        $server = new MCPHttpServer($port, $authKey, $host);
        $server->handleRequest();
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Internal server error',
            'details' => [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
