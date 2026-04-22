<?php

/** @var string $host */
/** @var int $port */
/** @var bool $authEnabled */
/** @var string $phpVersion */

$host ??= '0.0.0.0';
$port ??= 0;
$authEnabled ??= false;
$phpVersion ??= PHP_VERSION;

echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║              MCP HTTP SERVER (AMP VERSION)              ║\n";
echo "╠══════════════════════════════════════════════════════════╣\n";
echo "║ Адрес:            http://{$host}:{$port}\n";
echo "║ Хост:             {$host}\n";
echo "║ Порт:             {$port}\n";
echo "║ Авторизация:      " . ($authEnabled ? "Включена" : "Отключена") . "\n";
echo "║ PHP версия:       {$phpVersion}\n";
echo "╠══════════════════════════════════════════════════════════╣\n";
echo "║ Доступные эндпоинты:\n";
echo "║   GET  /api/commands    - Список команд\n";
echo "║   GET  /api/resources   - Список ресурсов\n";
echo "║   POST /api/execute     - Выполнение команды\n";
echo "║   GET  /api/health      - Проверка состояния\n";
echo "║   GET  /api/info        - Информация о сервере\n";
echo "║   GET  /api/metrics     - Метрики сервера\n";
echo "║   GET  /                - Корневая страница\n";
echo "╠══════════════════════════════════════════════════════════╣\n";
echo "║ Управление:\n";
echo "║   Ctrl+C              - Остановить сервер\n";
echo "║   SIGTERM/SIGINT      - Graceful shutdown\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";
