<?php

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/vendor/autoload.php';

use quanzo\mcp\MCPHttpServerAmp;

// Обработка аргументов командной строки
if (php_sapi_name() === 'cli') {
    $host = '0.0.0.0';
    $port = 8080;
    $authKey = 'default-secret-key-123';

    // Парсим аргументы командной строки
    if (isset($argv[1]) && !is_numeric($argv[1])) {
        $host = $argv[1];
    }

    if (isset($argv[2]) && is_numeric($argv[2])) {
        $port = (int) $argv[2];
    }

    if (isset($argv[3])) {
        $authKey = $argv[3];
    }

    // Проверяем порт
    if ($port < 1 || $port > 65535) {
        echo "Ошибка: Порт должен быть в диапазоне 1-65535\n";
        exit(1);
    }

    try {
        $server = new MCPHttpServerAmp($host, $port, $authKey);
        $server->run();
    } catch (\Throwable $e) {
        echo "Ошибка запуска сервера: " . $e->getMessage() . "\n";
        echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "Трассировка: " . $e->getTraceAsString() . "\n";
        exit(1);
    }
} else {
    echo "Этот скрипт должен быть запущен из командной строки\n";
    echo "Использование: php http_server_amp.php [хост] [порт] [ключ]\n";
    echo "Примеры:\n";
    echo "  php http_server_amp.php\n";
    echo "  php http_server_amp.php 0.0.0.0 8080\n";
    echo "  php http_server_amp.php 127.0.0.1 9005 my-secret-key\n";
    exit(1);
}

