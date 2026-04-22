<?php

namespace quanzo\mcp;

use Amp\ByteStream\WritableResourceStream;
use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Future;
use Amp\Http\Server\Driver\LoggingSocketClientFactory;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Socket\ResourceServerSocketFactory;
use Amp\Socket\ServerSocketFactory;
use Monolog\Logger;
use Revolt\EventLoop;
use quanzo\mcp\client\MCPClient;
use quanzo\mcp\http\HttpResponseFormatter;

/**
 * Продвинутый HTTP сервер для MCP на основе Amp
 *
 * Использует Amp (асинхронную библиотеку) для высокой производительности
 * и пул процессов для выполнения MCP команд.
 *
 * Требования:
 *   composer require amphp/http-server amphp/process amphp/socket
 *
 * Использование:
 *   php http_server_amp.php [хост] [порт] [ключ_авторизации]
 *
 * Примеры:
 *   php http_server_amp.php
 *   php http_server_amp.php 0.0.0.0 8080
 *   php http_server_amp.php 127.0.0.1 9000 my-secret-key-123
 */
class MCPHttpServerAmp
{
    private string $authKey;
    private ?string $mcpServerScript = null;
    private string $host;
    private int $port;
    private Logger $logger;
    private int $activeConnections = 0;
    private int $startTime;

    public function __construct(
        string $host = '0.0.0.0',
        int $port = 8080,
        string $authKey = 'default-secret-key-123'
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->authKey = $authKey;
        $this->mcpServerScript = $this->getProjectRoot() . '/bin/mcp_server.php';
        $this->startTime = time();

        // Проверяем существование скрипта MCP сервера
        if (!file_exists($this->mcpServerScript)) {
            throw new \RuntimeException("MCP сервер не найден: " . $this->mcpServerScript);
        }

        // Настраиваем логгер
        $this->setupLogger();
    }

    private function getProjectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Настраивает логгер
     */
    private function setupLogger(): void
    {
        $logHandler = new StreamHandler(new WritableResourceStream(STDOUT));
        $logHandler->setFormatter(new ConsoleFormatter());

        $this->logger = new Logger('mcp-http-server');
        $this->logger->pushHandler($logHandler);
    }

    /**
     * Запускает HTTP сервер
     */
    public function run(): void
    {
        $this->logger->info("Запуск MCP HTTP сервера (Amp) на http://{$this->host}:{$this->port}");
        $this->logger->info("Ключ авторизации: " . str_repeat('*', strlen($this->authKey)));

        // Создаем фабрику сокетов
        $serverSocketFactory = new ResourceServerSocketFactory();
        $clientSocketFactory = new SocketClientFactory($this->logger);

        // Создаем обработчик ошибок
        $errorHandler = new DefaultErrorHandler();

        $requestHandler = new ClosureRequestHandler(
            function (Request $request): Response {
                $a = \Amp\async(function () use ($request) {
                    $this->activeConnections++;
                    try {
                        return $this->handleRequest($request);
                    } catch (\Throwable $e) {
                        $this->logger->error("Ошибка обработки запроса: " . $e->getMessage());
                        return new Response(
                            HttpStatus::INTERNAL_SERVER_ERROR,
                            ['content-type' => 'application/json'],
                            json_encode(
                                HttpResponseFormatter::error(500, 'Internal server error'),
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                            )
                        );
                    } finally {
                        $this->activeConnections--;
                    }
                });
                return $a->await();
            }
        );

        // Создаем экземпляр сервера с правильными параметрами
        $server = new SocketHttpServer(
            logger: $this->logger,
            serverSocketFactory: $serverSocketFactory,
            clientFactory: $clientSocketFactory
        );

        $server->expose("{$this->host}:{$this->port}");
        $server->start($requestHandler, $errorHandler);

        // Регистрируем обработчик сигналов для graceful shutdown
        $signalHandler = function (string $watcherId) use ($server) {
            $this->logger->info("Получен сигнал завершения, останавливаем сервер...");
            $server->stop();
            EventLoop::cancel($watcherId);
        };

        EventLoop::onSignal(SIGINT, $signalHandler);
        EventLoop::onSignal(SIGTERM, $signalHandler);

        // Выводим информацию о сервере
        $this->printServerInfo();

        // Ждем завершения сервера
        EventLoop::run();

        $this->logger->info("Сервер остановлен");
    }

    /**
     * Выводит информацию о сервере
     */
    private function printServerInfo(): void
    {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════╗\n";
        echo "║              MCP HTTP SERVER (AMP VERSION)              ║\n";
        echo "╠══════════════════════════════════════════════════════════╣\n";
        echo "║ Адрес:            http://{$this->host}:{$this->port}\n";
        echo "║ Хост:             {$this->host}\n";
        echo "║ Порт:             {$this->port}\n";
        echo "║ Авторизация:      " . (!empty($this->authKey) ? "Включена" : "Отключена") . "\n";
        echo "║ PHP версия:       " . PHP_VERSION . "\n";
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

        $this->logger->info("Сервер готов к приему запросов");
    }

    /**
     * Обрабатывает HTTP запрос (синхронная часть)
     */
    private function handleRequest(Request $request): Response
    {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        $this->logger->debug("Запрос: {$method} {$path}");

        // Маршрутизация
        switch (true) {
            case $method === 'GET' && $path === '/api/commands':
                return $this->handleGetCommands($request);

            case $method === 'GET' && $path === '/api/resources':
                return $this->handleGetResources($request);

            case $method === 'POST' && $path === '/api/execute':
                return $this->handleExecuteCommandAsync($request);

            case $method === 'GET' && $path === '/api/health':
                return $this->handleHealthCheck($request);

            case $method === 'GET' && $path === '/api/info':
                return $this->handleServerInfo($request);

            case $method === 'GET' && ($path === '/' || $path === '/api'):
                return $this->handleApiRoot($request);

            case $method === 'OPTIONS':
                return $this->handleCorsPreflight($request);

            default:
                return $this->jsonResponse(
                    HttpStatus::NOT_FOUND,
                    ['error' => 'Endpoint not found']
                );
        }
    }

    /**
     * Асинхронная обработка POST /api/execute
     */
    private function handleExecuteCommandAsync(Request $request): Response
    {
        try {
            // Асинхронно читаем тело запроса
            $body = $request->getBody()->buffer();

            if (empty($body)) {
                return $this->jsonResponse(HttpStatus::BAD_REQUEST, [
                    'status' => 'error',
                    'message' => 'Empty request body'
                ]);
            }

            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->jsonResponse(HttpStatus::BAD_REQUEST, [
                    'status' => 'error',
                    'message' => 'Invalid JSON: ' . json_last_error_msg()
                ]);
            }

            if (!isset($data['command']) || !is_string($data['command'])) {
                return $this->jsonResponse(HttpStatus::BAD_REQUEST, [
                    'status' => 'error',
                    'message' => 'Field "command" is required and must be a string'
                ]);
            }

            $command = $data['command'];
            $params = $data['params'] ?? [];

            if (!is_array($params)) {
                return $this->jsonResponse(HttpStatus::BAD_REQUEST, [
                    'status' => 'error',
                    'message' => 'Field "params" must be an array'
                ]);
            }

            // Добавляем ключ авторизации, если не указан
            if (!isset($params['auth'])) {
                $params['auth'] = $this->authKey;
            }

            $this->logger->info("Выполнение команды: {$command}");

            // Выполняем команду через MCPClient
            $client = new MCPClient($this->mcpServerScript, $this->authKey);
            try {
                $result = $client->sendRequest($command, $params);
            } finally {
                $client->close();
            }

            return $this->jsonResponse(HttpStatus::OK, HttpResponseFormatter::success($result));
        } catch (\Throwable $e) {
            $this->logger->error("Ошибка выполнения команды: " . $e->getMessage());
            return $this->jsonResponse(
                HttpStatus::INTERNAL_SERVER_ERROR,
                HttpResponseFormatter::error(500, 'Command execution failed', ['error' => $e->getMessage()])
            );
        }
    }

    /**
     * Обрабатывает CORS preflight запрос
     */
    private function handleCorsPreflight(Request $request): Response
    {
        return new Response(HttpStatus::OK, [
            'access-control-allow-origin' => '*',
            'access-control-allow-methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'access-control-allow-headers' => 'Content-Type, Authorization',
            'access-control-max-age' => '86400',
        ]);
    }

    /**
     * Обрабатывает запрос получения списка команд
     */
    private function handleGetCommands(Request $request): Response
    {
        try {
            $client = new MCPClient($this->mcpServerScript, $this->authKey);
            try {
                $commands = $client->listCommands();
            } finally {
                $client->close();
            }

            return $this->jsonResponse(
                HttpStatus::OK,
                HttpResponseFormatter::success(['commands' => $commands, 'count' => count($commands)])
            );
        } catch (\Throwable $e) {
            $this->logger->error("Ошибка получения списка команд: " . $e->getMessage());
            return $this->jsonResponse(
                HttpStatus::INTERNAL_SERVER_ERROR,
                HttpResponseFormatter::error(500, 'Failed to get commands list', ['error' => $e->getMessage()])
            );
        }
    }

    /**
     * Обрабатывает запрос получения списка ресурсов
     */
    private function handleGetResources(Request $request): Response
    {
        try {
            $client = new MCPClient($this->mcpServerScript, $this->authKey);
            try {
                $resources = $client->listResources();
            } finally {
                $client->close();
            }

            return $this->jsonResponse(
                HttpStatus::OK,
                HttpResponseFormatter::success(['resources' => $resources, 'count' => count($resources)])
            );
        } catch (\Throwable $e) {
            $this->logger->error("Ошибка получения списка ресурсов: " . $e->getMessage());
            return $this->jsonResponse(
                HttpStatus::INTERNAL_SERVER_ERROR,
                HttpResponseFormatter::error(500, 'Failed to get resources list', ['error' => $e->getMessage()])
            );
        }
    }

    /**
     * Обрабатывает health check
     */
    private function handleHealthCheck(Request $request): Response
    {
        $data = [
            'message' => 'MCP HTTP Server (Amp) is running',
            'server' => [
                'name'               => 'MCP HTTP Server (Amp)',
                'version'            => '1.0.0',
                'php_version'        => PHP_VERSION,
                'host'               => $this->host,
                'port'               => $this->port,
                'auth_enabled'       => !empty($this->authKey),
                'active_connections' => $this->activeConnections
            ]
        ];
        return $this->jsonResponse(HttpStatus::OK, HttpResponseFormatter::success($data));
    }

    /**
     * Обрабатывает информацию о сервере
     */
    private function handleServerInfo(Request $request): Response
    {
        return $this->jsonResponse(HttpStatus::OK, [
            'status' => 'success',
            'data'   => [
                'server' => [
                    'name'               => 'MCP HTTP Gateway (Amp)',
                    'version'            => '0.0.1',
                    'description'        => 'Высокопроизводительный HTTP интерфейс для MCP сервера на основе Amp',
                    'php_version'        => PHP_VERSION,
                    'host'               => $this->host,
                    'port'               => $this->port,
                    'auth_enabled'       => !empty($this->authKey),
                    'auth_key_length'    => strlen($this->authKey),
                    'amp_version'        => '3.x',
                    'active_connections' => $this->activeConnections
                ],
                'endpoints' => [
                    'GET /api/commands'  => 'Список доступных команд',
                    'GET /api/resources' => 'Список доступных ресурсов',
                    'POST /api/execute'  => 'Выполнение команды',
                    'GET /api/health'    => 'Проверка состояния сервера',
                    'GET /api/info'      => 'Информация о сервере',
                    'GET /api/metrics'   => 'Метрики сервера'
                ],
                'performance' => [
                    'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB',
                    'memory_peak'  => memory_get_peak_usage(true) / 1024 / 1024 . ' MB',
                    'uptime'       => $this->getUptime()
                ],
                'mcp_server' => [
                    'script_path' => $this->mcpServerScript,
                    'exists'      => file_exists($this->mcpServerScript),
                    'file_size'   => file_exists($this->mcpServerScript) ?
                        filesize($this->mcpServerScript) : 0
                ]
            ],
            'timestamp' => date('c')
        ]);
    }

    /**
     * Обрабатывает метрики сервера
     */
    private function handleMetrics(Request $request): Response
    {
        $metrics = [
            'connections' => [
                'active' => $this->activeConnections
            ],
            'memory' => [
                'usage'       => memory_get_usage(true),
                'usage_human' => $this->formatBytes(memory_get_usage(true)),
                'peak'        => memory_get_peak_usage(true),
                'peak_human'  => $this->formatBytes(memory_get_peak_usage(true)),
                'limit'       => ini_get('memory_limit')
            ],
            'server' => [
                'uptime'      => $this->getUptime(),
                'php_version' => PHP_VERSION,
                'amp_version' => '3.x',
                'host'        => $this->host,
                'port'        => $this->port
            ],
            'timestamps' => [
                'current'     => time(),
                'current_iso' => date('c'),
                'started'     => $this->startTime,
                'started_iso' => date('c', $this->startTime)
            ]
        ];

        return $this->jsonResponse(HttpStatus::OK, HttpResponseFormatter::success($metrics));
    }

    /**
     * Обрабатывает корневой путь API
     */
    private function handleApiRoot(Request $request): Response
    {
        $memoryUsage = $this->formatBytes(memory_get_usage(true));
        $uptime = $this->getUptime();
        $currentYear = date('Y');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCP HTTP Server (Amp)</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
            padding: 20px;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .header { 
            text-align: center; 
            margin-bottom: 40px;
        }
        .header h1 { 
            font-size: 3em; 
            margin-bottom: 10px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .header p { 
            font-size: 1.2em; 
            opacity: 0.9;
        }
        .status { 
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .status-indicator { 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status-dot {
            width: 12px;
            height: 12px;
            background: #4CAF50;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .endpoints { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 20px; 
            margin-bottom: 30px;
        }
        .endpoint-card { 
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            transition: transform 0.3s, background 0.3s;
        }
        .endpoint-card:hover { 
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.2);
        }
        .method { 
            display: inline-block;
            padding: 4px 12px;
            border-radius: 5px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .get { background: #4CAF50; }
        .post { background: #2196F3; }
        .path { 
            font-family: monospace; 
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        .description { 
            opacity: 0.9;
            font-size: 0.95em;
        }
        .footer { 
            text-align: center; 
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.2);
            opacity: 0.7;
        }
        .stats { 
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .stat-card { 
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        .stat-value { 
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label { 
            font-size: 0.9em;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 MCP HTTP Server (Amp)</h1>
            <p>Высокопроизводительный HTTP интерфейс для MCP сервера</p>
        </div>
        
        <div class="status">
            <div class="status-indicator">
                <div class="status-dot"></div>
                <span>Сервер работает</span>
            </div>
            <div>Версия: 1.0.0 | Amp 3.x | PHP {$this->getPhpVersion()}</div>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value">{$this->activeConnections}</div>
                <div class="stat-label">Активных соединений</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{$memoryUsage}</div>
                <div class="stat-label">Использование памяти</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{$uptime}</div>
                <div class="stat-label">Время работы</div>
            </div>
        </div>
        
        <h2 style="margin: 30px 0 20px 0; color: #fff;">📡 Доступные эндпоинты</h2>
        
        <div class="endpoints">
            <div class="endpoint-card">
                <span class="method get">GET</span>
                <div class="path">/api/commands</div>
                <div class="description">Получить список доступных команд MCP</div>
            </div>
            
            <div class="endpoint-card">
                <span class="method get">GET</span>
                <div class="path">/api/resources</div>
                <div class="description">Получить список доступных ресурсов</div>
            </div>
            
            <div class="endpoint-card">
                <span class="method post">POST</span>
                <div class="path">/api/execute</div>
                <div class="description">Выполнить MCP команду</div>
            </div>
            
            <div class="endpoint-card">
                <span class="method get">GET</span>
                <div class="path">/api/health</div>
                <div class="description">Проверка состояния сервера</div>
            </div>
            
            <div class="endpoint-card">
                <span class="method get">GET</span>
                <div class="path">/api/info</div>
                <div class="description">Подробная информация о сервере</div>
            </div>
            
            <div class="endpoint-card">
                <span class="method get">GET</span>
                <div class="path">/api/metrics</div>
                <div class="description">Метрики производительности</div>
            </div>
        </div>
        
        <div class="footer">
            <p>MCP HTTP Server (Amp) v1.0.0</p>
            <p>Адрес сервера: http://{$this->host}:{$this->port}</p>
            <p>© {$currentYear} MCP Server Team</p>
        </div>
    </div>
    
    <script>
        // Обновление статистики каждые 5 секунд
        async function updateStats() {
            try {
                const response = await fetch('/api/health');
                const data = await response.json();
                
                if (data.server) {
                    document.querySelector('.stat-value:nth-child(1)').textContent = 
                        data.server.active_connections || 0;
                    
                    // Можно обновлять другие метрики по мере необходимости
                }
            } catch (error) {
                console.error('Failed to update stats:', error);
            }
        }
        
        // Обновляем статистику каждые 5 секунд
        setInterval(updateStats, 5000);
        
        // Первоначальное обновление
        updateStats();
    </script>
</body>
</html>
HTML;

        return new Response(
            HttpStatus::OK,
            ['content-type' => 'text/html; charset=utf-8'],
            $html
        );
    }

    /**
     * Создает JSON ответ
     */
    private function jsonResponse(int $statusCode, array $data): Response
    {
        return new Response(
            $statusCode,
            [
                'content-type' => 'application/json',
                'access-control-allow-origin' => '*',
                'cache-control' => 'no-cache, no-store, must-revalidate'
            ],
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Форматирует байты в читаемый вид
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Возвращает время работы сервера в читаемом формате
     */
    private function getUptime(): string
    {
        $uptime = time() - $this->startTime;

        $days = floor($uptime / 86400);
        $hours = floor(($uptime % 86400) / 3600);
        $minutes = floor(($uptime % 3600) / 60);
        $seconds = $uptime % 60;

        if ($days > 0) {
            return sprintf('%dд %dч %dм %dс', $days, $hours, $minutes, $seconds);
        } elseif ($hours > 0) {
            return sprintf('%dч %dм %dс', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%dм %dс', $minutes, $seconds);
        } else {
            return sprintf('%dс', $seconds);
        }
    }

    /**
     * Возвращает версию PHP для отображения
     */
    private function getPhpVersion(): string
    {
        return PHP_VERSION;
    }
}
