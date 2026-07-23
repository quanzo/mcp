<?php

declare(strict_types=1);

namespace quanzo\mcp\classes;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use quanzo\mcp\classes\dto\JsonRpcRequest;
use quanzo\mcp\classes\dto\JsonRpcResponse;
use quanzo\mcp\classes\dto\mcp\CallToolResult;
use quanzo\mcp\classes\dto\mcp\InitializeResult;
use quanzo\mcp\classes\dto\mcp\ReadResourceResult;
use quanzo\mcp\classes\dto\mcp\ResourceDefinition;
use quanzo\mcp\classes\dto\mcp\ServerInfo;
use quanzo\mcp\classes\dto\mcp\ToolDefinition;
use quanzo\mcp\classes\validation\ValidationException;
use quanzo\mcp\helpers\JsonHelper;
use quanzo\mcp\interfaces\CommandInterface;
use quanzo\mcp\interfaces\ResourceInterface;

/**
 * Класс McpServer
 *
 * Транспортно-независимое ядро MCP: lifecycle initialize, tools/*, resources/*, ping.
 * Транспорты (stdio / Streamable HTTP) только доставляют JSON-RPC сообщения сюда.
 *
 * Пример использования:
 *   $server = new McpServer(new ServerInfo('quanzo-mcp', '1.0.0'), $logger);
 *   $server->registerCommand(new EchoCommand());
 *   $response = $server->handleMessage($requestArray); // null для notification
 */
class McpServer
{
    /**
     * Версия протокола по умолчанию (если клиент прислал неизвестную)
     *
     * @var string
     */
    public const DEFAULT_PROTOCOL_VERSION = '2025-03-26';

    /**
     * Поддерживаемые версии протокола
     *
     * @var list<string>
     */
    private const SUPPORTED_PROTOCOL_VERSIONS = [
        '2024-11-05',
        '2025-03-26',
        '2025-06-18',
        '2025-11-25',
    ];

    /**
     * Методы, допустимые до завершения initialize
     *
     * @var list<string>
     */
    private const PRE_INIT_METHODS = [
        'initialize',
        'ping',
    ];

    /**
     * Зарегистрированные инструменты (commands)
     *
     * @var array<string, CommandInterface>
     */
    private array $commands = [];

    /**
     * Зарегистрированные ресурсы
     *
     * @var array<string, ResourceInterface>
     */
    private array $resources = [];

    /**
     * Информация о сервере
     *
     * @var ServerInfo
     */
    private ServerInfo $serverInfo;

    /**
     * Логгер
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Флаг: initialize уже выполнен (достаточно для вызова tools/*)
     *
     * @var bool
     */
    private bool $initializeDone = false;

    /**
     * Согласованная версия протокола
     *
     * @var string|null
     */
    private ?string $protocolVersion = null;

    /**
     * Проверяет, поддерживается ли версия протокола
     *
     * @param string $version Версия (например 2025-03-26)
     *
     * @return bool
     */
    public static function isSupportedProtocolVersion(string $version): bool
    {
        return in_array($version, self::SUPPORTED_PROTOCOL_VERSIONS, true);
    }

    /**
     * Конструктор McpServer
     *
     * @param ServerInfo $serverInfo Идентификация сервера
     * @param LoggerInterface|null $logger Логгер
     */
    public function __construct(ServerInfo $serverInfo, ?LoggerInterface $logger = null)
    {
        $this->serverInfo = $serverInfo;
        $this->logger = $logger ?? new NullLogger();
        $this->logger->info('McpServer created', [
            'name' => $serverInfo->getName(),
            'version' => $serverInfo->getVersion(),
        ]);
    }

    /**
     * Создаёт новый экземпляр с теми же tools/resources (для HTTP-сессий)
     *
     * @return self
     */
    public function createSessionInstance(): self
    {
        $copy = new self($this->serverInfo, $this->logger);
        $copy->commands = $this->commands;
        $copy->resources = $this->resources;

        return $copy;
    }

    /**
     * Регистрирует инструмент (command)
     *
     * @param CommandInterface $command Команда
     *
     * @return self
     */
    public function registerCommand(CommandInterface $command): self
    {
        $this->commands[$command->getName()] = $command;
        $this->logger->info('Command registered', ['command' => $command->getName()]);

        return $this;
    }

    /**
     * Регистрирует ресурс
     *
     * @param ResourceInterface $resource Ресурс
     *
     * @return self
     */
    public function registerResource(ResourceInterface $resource): self
    {
        $this->resources[$resource->getUri()] = $resource;
        $this->logger->info('Resource registered', ['uri' => $resource->getUri()]);

        return $this;
    }

    /**
     * Проверяет, выполнен ли initialize
     *
     * @return bool
     */
    public function isInitializeDone(): bool
    {
        return $this->initializeDone;
    }

    /**
     * Обрабатывает одно JSON-RPC сообщение
     *
     * Возвращает массив ответа или null для notification (без поля id).
     * На неверных данных не бросает наружу: отдаёт JSON-RPC error.
     *
     * @param array<string, mixed> $message Десериализованное сообщение
     *
     * @return array<string, mixed>|null Ответ или null для notification
     */
    public function handleMessage(array $message): ?array
    {
        $rpc = JsonRpcRequest::fromArray($message);
        $id = $rpc->getId();
        $method = $rpc->getMethod();
        $params = $rpc->getParams();
        $isNotification = $rpc->isNotification();

        $this->logger->info('Processing MCP message', [
            'id' => $id,
            'method' => $method,
            'notification' => $isNotification,
        ]);

        if ($method === '') {
            if ($isNotification) {
                return null;
            }

            return JsonRpcResponse::error($id, -32600, 'Invalid Request: missing method')->toArray();
        }

        // Спец-notification завершения handshake — ответа не шлём
        if ($method === 'notifications/initialized') {
            $this->logger->info('Client sent notifications/initialized');

            return null;
        }

        if ($isNotification) {
            // Неизвестные notifications игнорируем (спека допускает)
            $this->logger->debug('Ignoring unknown notification', ['method' => $method]);

            return null;
        }

        // До initialize обычные методы запрещены (кроме ping)
        if (!$this->initializeDone && !in_array($method, self::PRE_INIT_METHODS, true)) {
            return JsonRpcResponse::error(
                $id,
                -32002,
                'Server not initialized'
            )->toArray();
        }

        try {
            return match ($method) {
                'initialize' => $this->handleInitialize($id, $params),
                'ping' => JsonRpcResponse::success($id, new \stdClass())->toArray(),
                'tools/list' => $this->handleToolsList($id),
                'tools/call' => $this->handleToolsCall($id, $params),
                'resources/list' => $this->handleResourcesList($id),
                'resources/read' => $this->handleResourcesRead($id, $params),
                default => JsonRpcResponse::error($id, -32601, "Method not found: {$method}")->toArray(),
            };
        } catch (ValidationException $e) {
            return JsonRpcResponse::error(
                $id,
                -32602,
                'Invalid params',
                ['validation_errors' => $e->getValidationErrors()]
            )->toArray();
        } catch (\InvalidArgumentException $e) {
            return JsonRpcResponse::error($id, -32602, $e->getMessage())->toArray();
        } catch (\Throwable $e) {
            // Любой неожиданный сбой → контролируемый Internal error, без падения транспорта
            $this->logger->error('Internal error', [
                'message' => $e->getMessage(),
                'method' => $method,
            ]);

            return JsonRpcResponse::error($id, -32603, 'Internal error', $e->getMessage())->toArray();
        }
    }

    /**
     * Обрабатывает initialize
     *
     * @param string|int|null $id Идентификатор запроса
     * @param array<string, mixed> $params Параметры
     *
     * @return array<string, mixed>
     */
    private function handleInitialize($id, array $params): array
    {
        $requested = isset($params['protocolVersion']) && is_string($params['protocolVersion'])
            ? $params['protocolVersion']
            : self::DEFAULT_PROTOCOL_VERSION;

        $this->protocolVersion = in_array($requested, self::SUPPORTED_PROTOCOL_VERSIONS, true)
            ? $requested
            : self::DEFAULT_PROTOCOL_VERSION;

        // initialize считается выполненным до notifications/initialized —
        // иначе клиент не сможет вызвать tools/list сразу после handshake
        $this->initializeDone = true;

        $capabilities = new \stdClass();
        if ($this->commands !== []) {
            $capabilities->tools = new \stdClass();
        }
        if ($this->resources !== []) {
            $capabilities->resources = new \stdClass();
        }

        $result = new InitializeResult(
            $this->protocolVersion,
            $capabilities,
            $this->serverInfo
        );

        $this->logger->info('Initialize completed', [
            'protocolVersion' => $this->protocolVersion,
        ]);

        return JsonRpcResponse::success($id, $result->toArray())->toArray();
    }

    /**
     * Обрабатывает tools/list
     *
     * @param string|int|null $id Идентификатор запроса
     *
     * @return array<string, mixed>
     */
    private function handleToolsList($id): array
    {
        $tools = [];
        foreach ($this->commands as $command) {
            $tools[] = (new ToolDefinition(
                $command->getName(),
                $command->getDescription(),
                $command->getInputSchema()
            ))->toArray();
        }

        return JsonRpcResponse::success($id, ['tools' => $tools])->toArray();
    }

    /**
     * Обрабатывает tools/call
     *
     * @param string|int|null $id Идентификатор запроса
     * @param array<string, mixed> $params Параметры
     *
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException Если имя инструмента отсутствует
     * @throws ValidationException Если аргументы не прошли валидацию
     */
    private function handleToolsCall($id, array $params): array
    {
        $name = $params['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('Missing required parameter: name');
        }

        if (!isset($this->commands[$name])) {
            return JsonRpcResponse::error($id, -32602, "Unknown tool: {$name}")->toArray();
        }

        $arguments = $params['arguments'] ?? [];
        if (!is_array($arguments)) {
            throw new \InvalidArgumentException('Parameter arguments must be an object');
        }

        $command = $this->commands[$name];

        try {
            $data = $command->execute($arguments);
            // Успех: произвольный array tool → MCP text content
            $callResult = CallToolResult::fromData(
                $data,
                static fn ($value): string => JsonHelper::encode($value)
            );

            return JsonRpcResponse::success($id, $callResult->toArray())->toArray();
        } catch (ValidationException $e) {
            // Схема — протокольная ошибка params
            throw $e;
        } catch (\Throwable $e) {
            // Бизнес/runtime ошибки tool → isError в result (не JSON-RPC error)
            $callResult = CallToolResult::errorText($e->getMessage());

            return JsonRpcResponse::success($id, $callResult->toArray())->toArray();
        }
    }

    /**
     * Обрабатывает resources/list
     *
     * @param string|int|null $id Идентификатор запроса
     *
     * @return array<string, mixed>
     */
    private function handleResourcesList($id): array
    {
        $resources = [];
        foreach ($this->resources as $resource) {
            $resources[] = (new ResourceDefinition(
                $resource->getUri(),
                $resource->getName(),
                $resource->getMimeType(),
                $resource->getDescription()
            ))->toArray();
        }

        return JsonRpcResponse::success($id, ['resources' => $resources])->toArray();
    }

    /**
     * Обрабатывает resources/read
     *
     * @param string|int|null $id Идентификатор запроса
     * @param array<string, mixed> $params Параметры
     *
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException Если uri отсутствует
     */
    private function handleResourcesRead($id, array $params): array
    {
        $uri = $params['uri'] ?? null;
        if (!is_string($uri) || $uri === '') {
            throw new \InvalidArgumentException('Missing required parameter: uri');
        }

        $resource = $this->findResource($uri);
        if ($resource === null) {
            return JsonRpcResponse::error($id, -32002, "Resource not found: {$uri}")->toArray();
        }

        $content = $resource->getContent($uri);
        $result = ReadResourceResult::text($uri, $resource->getMimeType(), $content);

        return JsonRpcResponse::success($id, $result->toArray())->toArray();
    }

    /**
     * Находит ресурс по URI
     *
     * @param string $uri URI
     *
     * @return ResourceInterface|null
     */
    private function findResource(string $uri): ?ResourceInterface
    {
        if (isset($this->resources[$uri])) {
            return $this->resources[$uri];
        }

        foreach ($this->resources as $resource) {
            if ($resource->matchesUri($uri)) {
                return $resource;
            }
        }

        return null;
    }
}
