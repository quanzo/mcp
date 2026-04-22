<?php

namespace quanzo\mcp;

use quanzo\mcp\client\MCPClient;
use quanzo\mcp\http\HttpResponseFormatter;

/**
 * HTTP сервер для MCP сервера
 *
 * Преобразует HTTP запросы в stdio запросы для MCP сервера
 * и возвращает HTTP ответы.
 *
 * Использование:
 *   php http_server.php [порт] [ключ_авторизации]
 *
 * Примеры:
 *   php http_server.php 8080
 *   php http_server.php 8080 my-secret-key-123
 */
class MCPHttpServer
{
    private string $authKey;
    private string $mcpServerScript;
    private int $port;
    private string $host;

    public function __construct(
        int $port = 8080,
        string $authKey = 'default-secret-key-123',
        string $host = '0.0.0.0'
    ) {
        $this->port = $port;
        $this->authKey = $authKey;
        $this->host = $host;
        $this->mcpServerScript = $this->getProjectRoot() . '/bin/mcp_server.php';

        // Проверяем существование скрипта MCP сервера
        if (!file_exists($this->mcpServerScript)) {
            throw new \RuntimeException("MCP сервер не найден: " . $this->mcpServerScript);
        }
    }

    private function getProjectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Обрабатывает HTTP запрос
     */
    public function handleRequest(): void
    {
        // Устанавливаем заголовки по умолчанию
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        // Обработка CORS preflight запросов
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        // Разбираем путь запроса
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        try {
            // Создаем клиент для каждого запроса
            $client = new MCPClient($this->mcpServerScript, $this->authKey);

            // Маршрутизация
            if ($method === 'GET' && $path === '/api/commands') {
                $this->handleGetCommands($client);
            } elseif ($method === 'GET' && $path === '/api/resources') {
                $this->handleGetResources($client);
            } elseif ($method === 'POST' && $path === '/api/execute') {
                $this->handleExecuteCommand($client);
            } elseif ($method === 'GET' && $path === '/api/health') {
                $this->handleHealthCheck();
            } elseif ($method === 'GET' && $path === '/api/info') {
                $this->handleServerInfo();
            } elseif ($path === '/' || $path === '/api') {
                $this->handleApiRoot();
            } else {
                $this->sendError(404, 'Endpoint not found');
            }

            // Закрываем клиента
            $client->close();
        } catch (\Throwable $e) {
            $this->sendError(500, 'Internal server error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Обрабатывает запрос получения списка команд
     */
    private function handleGetCommands(MCPClient $client): void
    {
        $commands = $client->listCommands();
        echo json_encode(
            HttpResponseFormatter::success(['commands' => $commands, 'count' => count($commands)]),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Обрабатывает запрос получения списка ресурсов
     */
    private function handleGetResources(MCPClient $client): void
    {
        $resources = $client->listResources();
        echo json_encode(
            HttpResponseFormatter::success(['resources' => $resources, 'count' => count($resources)]),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Обрабатывает запрос выполнения команды
     */
    private function handleExecuteCommand(MCPClient $client): void
    {
        // Получаем тело запроса
        $input = file_get_contents('php://input');

        if (empty($input)) {
            $this->sendError(400, 'Empty request body');
            return;
        }

        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError(400, 'Invalid JSON: ' . json_last_error_msg());
            return;
        }

        if (!isset($data['command']) || !is_string($data['command'])) {
            $this->sendError(400, 'Field "command" is required and must be a string');
            return;
        }

        $command = $data['command'];
        $params = $data['params'] ?? [];

        if (!is_array($params)) {
            $this->sendError(400, 'Field "params" must be an array');
            return;
        }

        // Проверяем наличие обязательного ключа авторизации
        if (empty($params['auth'])) {
            $params['auth'] = $this->authKey;
        }

        try {
            $result = $client->sendRequest($command, $params);
            echo json_encode(
                HttpResponseFormatter::success($result),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (\Throwable $e) {
            $this->sendError(500, 'Command execution failed', [
                'message' => $e->getMessage(),
                'command' => $command
            ]);
        }
    }

    /**
     * Обрабатывает health check
     */
    private function handleHealthCheck(): void
    {
        $data = [
            'message' => 'MCP HTTP Server is running',
            'server' => [
                'name' => 'MCP HTTP Server',
                'version' => '1.0.0',
                'php_version' => PHP_VERSION,
                'host' => $this->host,
                'port' => $this->port,
                'auth_enabled' => !empty($this->authKey)
            ]
        ];
        echo json_encode(
            HttpResponseFormatter::success($data),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Обрабатывает информацию о сервере
     */
    private function handleServerInfo(): void
    {
        $data = [
            'server' => [
                'name' => 'MCP HTTP Gateway',
                'version' => '1.0.0',
                'description' => 'HTTP интерфейс для MCP сервера',
                'php_version' => PHP_VERSION,
                'host' => $this->host,
                'port' => $this->port,
                'auth_enabled' => !empty($this->authKey),
                'auth_key_length' => strlen($this->authKey)
            ],
            'endpoints' => [
                'GET /api/commands' => 'Список доступных команд',
                'GET /api/resources' => 'Список доступных ресурсов',
                'POST /api/execute' => 'Выполнение команды',
                'GET /api/health' => 'Проверка состояния сервера',
                'GET /api/info' => 'Информация о сервере'
            ],
            'mcp_server' => [
                'script_path' => $this->mcpServerScript,
                'exists' => file_exists($this->mcpServerScript)
            ]
        ];
        echo json_encode(
            HttpResponseFormatter::success($data),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Обрабатывает корневой путь API
     */
    private function handleApiRoot(): void
    {
        $data = [
            'message' => 'MCP HTTP Server API',
            'endpoints' => [
                '/api/commands' => 'GET - Список команд',
                '/api/resources' => 'GET - Список ресурсов',
                '/api/execute' => 'POST - Выполнение команды',
                '/api/health' => 'GET - Проверка состояния',
                '/api/info' => 'GET - Информация о сервере'
            ],
            'documentation' => 'https://github.com/your-repo/mcp-server'
        ];
        echo json_encode(
            HttpResponseFormatter::success($data),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Отправляет ошибку в формате JSON
     */
    private function sendError(int $code, string $message, array $details = []): void
    {
        http_response_code($code);
        echo json_encode(
            HttpResponseFormatter::error($code, $message, $details),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Запускает встроенный веб-сервер PHP
     */
    public function run(): void
    {
        echo "Запуск MCP HTTP сервера на http://{$this->host}:{$this->port}\n";
        echo "Нажмите Ctrl+C для остановки\n\n";

        echo "Доступные эндпоинты:\n";
        echo "  http://{$this->host}:{$this->port}/api/commands\n";
        echo "  http://{$this->host}:{$this->port}/api/resources\n";
        echo "  http://{$this->host}:{$this->port}/api/health\n";
        echo "  http://{$this->host}:{$this->port}/api/info\n";
        echo "  http://{$this->host}:{$this->port}/api/execute (POST)\n\n";

        echo "Используемый ключ авторизации: " . str_repeat('*', strlen($this->authKey)) . "\n";
        echo "Длина ключа: " . strlen($this->authKey) . " символов\n\n";

        echo "Логирование:\n";
        echo "  Логи MCP сервера: " . $this->getProjectRoot() . "/logs/mcp-server.log\n";
        echo "  Логи HTTP сервера: вывод в консоль\n\n";

        // Запускаем встроенный сервер PHP
        exec("php -S {$this->host}:{$this->port} " . __FILE__);
    }
}
