<?php

declare(strict_types=1);

namespace quanzo\mcp\classes\client;

use Psr\Log\LoggerInterface;
use quanzo\mcp\helpers\JsonHelper;

/**
 * Класс MCPClient
 *
 * Клиент стандартного MCP поверх stdio: initialize → tools/list|call, resources/*.
 *
 * Пример использования:
 *   $client = new MCPClient(__DIR__ . '/../../bin/mcp_server.php');
 *   $tools = $client->listTools();
 *   $result = $client->callTool('echo', ['message' => 'hi']);
 */
class MCPClient
{
    /**
     * Дочерний процесс сервера
     *
     * @var resource
     */
    private $process;

    /**
     * Каналы общения с дочерним процессом
     *
     * @var array<int, resource>
     */
    private array $pipes = [];

    /**
     * Счётчик идентификаторов запросов
     *
     * @var int
     */
    private int $requestId = 1;

    /**
     * Логгер
     *
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger;

    /**
     * Флаг успешного initialize
     *
     * @var bool
     */
    private bool $initialized = false;

    /**
     * Конструктор MCPClient
     *
     * @param string $serverScript Путь к скрипту сервера
     * @param LoggerInterface|null $logger Логгер
     * @param bool $autoInitialize Выполнить handshake сразу
     *
     * @throws \RuntimeException Если не удалось запустить MCP сервер
     */
    public function __construct(
        string $serverScript,
        ?LoggerInterface $logger = null,
        bool $autoInitialize = true
    ) {
        $this->logger = $logger;

        $command = 'php ' . escapeshellarg($serverScript);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $this->process = proc_open($command, $descriptors, $this->pipes);

        if (!is_resource($this->process)) {
            throw new \RuntimeException('Не удалось запустить MCP сервер');
        }

        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);

        if ($this->logger !== null) {
            $status = proc_get_status($this->process);
            $this->logger->info('MCP сервер запущен', ['pid' => $status['pid'] ?? null]);
        }

        if ($autoInitialize) {
            $this->initialize();
        }
    }

    /**
     * Выполняет handshake initialize + notifications/initialized
     *
     * @param string $protocolVersion Запрашиваемая версия протокола
     *
     * @return array<string, mixed> Результат initialize
     */
    public function initialize(string $protocolVersion = '2025-03-26'): array
    {
        $result = $this->sendRequest('initialize', [
            'protocolVersion' => $protocolVersion,
            'capabilities' => new \stdClass(),
            'clientInfo' => [
                'name' => 'quanzo-mcp-client',
                'version' => '1.0.0',
            ],
        ]);

        $this->sendNotification('notifications/initialized');
        $this->initialized = true;

        return $result;
    }

    /**
     * Отправляет JSON-RPC notification (без ожидания ответа)
     *
     * @param string $method Метод
     * @param array<string, mixed> $params Параметры
     *
     * @return void
     */
    public function sendNotification(string $method, array $params = []): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => $method,
        ];
        if ($params !== []) {
            $request['params'] = $params;
        }

        fwrite($this->pipes[0], JsonHelper::encode($request) . "\n");
        fflush($this->pipes[0]);
    }

    /**
     * Отправляет JSON-RPC запрос и ждёт ответ
     *
     * @param string $method Метод
     * @param array<string, mixed> $params Параметры
     * @param int|null $id Идентификатор
     *
     * @return array<string, mixed> Поле result
     *
     * @throws \RuntimeException При ошибке или таймауте
     */
    public function sendRequest(string $method, array $params = [], ?int $id = null): array
    {
        $id = $id ?? $this->requestId++;

        $request = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => $params === [] ? new \stdClass() : $params,
        ];

        $jsonRequest = JsonHelper::encode($request) . "\n";
        fwrite($this->pipes[0], $jsonRequest);
        fflush($this->pipes[0]);

        if ($this->logger !== null) {
            $this->logger->info('Отправлен запрос', ['id' => $id, 'method' => $method]);
        }

        $response = $this->readResponse($id);

        if (isset($response['error'])) {
            $message = $response['error']['message'] ?? 'Unknown error';
            throw new \RuntimeException('MCP error: ' . $message);
        }

        /** @var array<string, mixed> $result */
        $result = $response['result'] ?? [];

        return is_array($result) ? $result : [];
    }

    /**
     * Возвращает список инструментов
     *
     * @return list<array<string, mixed>>
     */
    public function listTools(): array
    {
        $result = $this->sendRequest('tools/list');
        $tools = $result['tools'] ?? [];

        return is_array($tools) ? array_values($tools) : [];
    }

    /**
     * Вызывает инструмент
     *
     * @param string $name Имя инструмента
     * @param array<string, mixed> $arguments Аргументы
     *
     * @return array<string, mixed> CallToolResult
     */
    public function callTool(string $name, array $arguments = []): array
    {
        return $this->sendRequest('tools/call', [
            'name' => $name,
            'arguments' => $arguments,
        ]);
    }

    /**
     * Возвращает список ресурсов
     *
     * @return list<array<string, mixed>>
     */
    public function listResources(): array
    {
        $result = $this->sendRequest('resources/list');
        $resources = $result['resources'] ?? [];

        return is_array($resources) ? array_values($resources) : [];
    }

    /**
     * Читает ресурс по URI
     *
     * @param string $uri URI ресурса
     *
     * @return array<string, mixed> ReadResourceResult
     */
    public function readResource(string $uri): array
    {
        return $this->sendRequest('resources/read', ['uri' => $uri]);
    }

    /**
     * Отправляет ping
     *
     * @return array<string, mixed>
     */
    public function ping(): array
    {
        return $this->sendRequest('ping');
    }

    /**
     * Закрывает соединение
     *
     * @return void
     */
    public function close(): void
    {
        if (isset($this->pipes[0]) && is_resource($this->pipes[0])) {
            fclose($this->pipes[0]);
        }
        if (isset($this->pipes[1]) && is_resource($this->pipes[1])) {
            fclose($this->pipes[1]);
        }
        if (isset($this->pipes[2]) && is_resource($this->pipes[2])) {
            fclose($this->pipes[2]);
        }
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
    }

    /**
     * Деструктор
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Читает ответ с ожидаемым id
     *
     * @param int $expectedId Ожидаемый id
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    private function readResponse(int $expectedId): array
    {
        $buffer = '';
        $start = microtime(true);
        $timeout = 5.0;

        while ((microtime(true) - $start) < $timeout) {
            $chunk = fread($this->pipes[1], 8192);
            if ($chunk !== false && $chunk !== '') {
                $buffer .= $chunk;
                if (str_contains($buffer, "\n")) {
                    $line = strtok($buffer, "\n");
                    if ($line !== false && $line !== '') {
                        /** @var array<string, mixed> $decoded */
                        $decoded = JsonHelper::decode($line, true);
                        if (($decoded['id'] ?? null) === $expectedId) {
                            return $decoded;
                        }
                    }
                }
            }

            $err = fread($this->pipes[2], 8192);
            if ($err !== false && $err !== '' && $this->logger !== null) {
                $this->logger->debug('stderr', ['data' => $err]);
            }

            usleep(10000);
        }

        throw new \RuntimeException('Timeout waiting for MCP response');
    }
}
