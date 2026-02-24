<?php
/**
 * Класс Server
 * 
 * Основной класс MCP сервера.
 * Реализует обработку входящих запросов, маршрутизацию команд,
 * управление ресурсами и авторизацию.
 * Работает через стандартные потоки ввода/вывода (stdio).
 */
namespace app\modules\neuron\mcp;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use app\modules\neuron\mcp\interfaces\CommandInterface;
use app\modules\neuron\mcp\interfaces\ResourceInterface;
use app\modules\neuron\mcp\commands\ValidationException;

class Server
{
    /**
     * Зарегистрированные команды
     * @var array<string, CommandInterface>
     */
    private array $commands = [];
    
    /**
     * Зарегистрированные ресурсы
     * @var array<string, ResourceInterface>
     */
    private array $resources = [];
    
    /**
     * Ключ авторизации (null если авторизация отключена)
     * @var string|null
     */
    private ?string $authKey = null;
    
    /**
     * Логгер для записи событий сервера
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    
    /**
     * Конструктор сервера
     * 
     * @param string|null $authKey Ключ авторизации (null для отключения авторизации)
     * @param LoggerInterface|null $logger Логгер для записи событий (по умолчанию NullLogger)
     */
    public function __construct(
        ?string $authKey = null,
        ?LoggerInterface $logger = null
    ) {
        $this->authKey = $authKey;
        $this->logger = $logger ?: new NullLogger();
        $this->logger->info('MCP Server initialized', [
            'php_version' => PHP_VERSION,
            'auth_enabled' => !is_null($authKey)
        ]);
    }
    
    /**
     * Регистрирует команду в сервере
     * 
     * @param CommandInterface $command Команда для регистрации
     * 
     * @return void
     */
    public function registerCommand(CommandInterface $command): void
    {
        $this->commands[$command->getName()] = $command;
        $this->logger->info('Command registered', ['command' => $command->getName()]);
    }
    
    /**
     * Регистрирует ресурс в сервере
     * 
     * @param ResourceInterface $resource Ресурс для регистрации
     * 
     * @return void
     */
    public function registerResource(ResourceInterface $resource): void
    {
        $this->resources[$resource->getUri()] = $resource;
        $this->logger->info('Resource registered', ['uri' => $resource->getUri()]);
    }
    
    /**
     * Запускает сервер в режиме stdio
     * 
     * Читает запросы из STDIN, обрабатывает их и отправляет ответы в STDOUT.
     * Работает в бесконечном цикле до закрытия входного потока.
     * 
     * @return void
     */
    public function run(): void
    {
        $this->logger->info('MCP server started');
        
        while (!feof(STDIN)) {
            $line = fgets(STDIN);
            
            if (empty($line)) {
                continue;
            }
            
            $this->logger->debug('Request received', ['raw' => trim($line)]);
            
            try {
                $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $response = $this->handleRequest($request);
                
                fwrite(STDOUT, json_encode($response) . "\n");
                $this->logger->debug('Response sent');
                
            } catch (\JsonException $e) {
                $this->sendError(null, -32700, "Parse error", $e->getMessage());
            } catch (\Exception $e) {
                $this->sendError($request['id'] ?? null, -32603, "Internal error", $e->getMessage());
            }
        }
        
        $this->logger->info('Server stopped');
    }
    
    /**
     * Обрабатывает входящий запрос
     * 
     * @param array $request Массив запроса в формате JSON-RPC 2.0
     * 
     * @return array Массив ответа в формате JSON-RPC 2.0
     * 
     * @throws \RuntimeException Если произошла ошибка авторизации
     * @throws \InvalidArgumentException Если запрос некорректен
     */
    private function handleRequest(array $request): array
    {
        $id     = $request['id'] ?? null;
        $method = $request['method'] ?? '';
        $params = $request['params'] ?? [];
        
        $this->logger->info('Processing request', [
            'id'     => $id,
            'method' => $method
        ]);
        
        // Проверка авторизации
        if ($this->authKey) {
            $requestAuthKey = $params['auth'] ?? null;
            if ($requestAuthKey !== $this->authKey) {
                throw new \RuntimeException('Authentication failed: Invalid auth key');
            }
            // Удаляем auth из параметров, чтобы не мешал командам
            unset($params['auth']);
        }
        
        // Обработка MCP методов
        switch ($method) {
            case 'mcp.listCommands':
                return $this->handleListCommands($id);
                
            case 'mcp.listResources':
                return $this->handleListResources($id);
                
            case 'mcp.readResource':
                return $this->handleReadResource($id, $params);
                
            default:
                return $this->handleCommand($id, $method, $params);
        }
    }
    
    /**
     * Обрабатывает запрос списка команд
     * 
     * @param string|null $id Идентификатор запроса
     * 
     * @return array Ответ со списком команд
     */
    private function handleListCommands(?string $id): array
    {
        $commandsInfo = [];
        
        foreach ($this->commands as $command) {
            $commandsInfo[] = [
                'name'        => $command->getName(),
                'description' => $command->getDescription(),
                'inputSchema' => $command->getInputSchema()
            ];
        }
        
        $this->logger->debug('Listed commands', ['count' => count($commandsInfo)]);
        
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'commands' => $commandsInfo
            ]
        ];
    }
    
    /**
     * Обрабатывает запрос списка ресурсов
     * 
     * @param string|null $id Идентификатор запроса
     * 
     * @return array Ответ со списком ресурсов
     */
    private function handleListResources(?string $id): array
    {
        $resourcesInfo = [];
        
        foreach ($this->resources as $resource) {
            $resourcesInfo[] = [
                'uri' => $resource->getUri(),
                'mimeType' => $resource->getMimeType(),
                'metadata' => $resource->getMetadata()
            ];
        }
        
        $this->logger->debug('Listed resources', ['count' => count($resourcesInfo)]);
        
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'resources' => $resourcesInfo
            ]
        ];
    }
    
    /**
     * Обрабатывает запрос чтения ресурса
     * 
     * @param string|null $id Идентификатор запроса
     * @param array $params Параметры запроса
     * 
     * @return array Ответ с содержимым ресурса
     * 
     * @throws \InvalidArgumentException Если отсутствует параметр uri
     * @throws \RuntimeException Если ресурс не найден
     */
    private function handleReadResource(?string $id, array $params): array
    {
        $uri = $params['uri'] ?? null;
        
        if (!$uri) {
            throw new \InvalidArgumentException('Missing required parameter: uri');
        }
        
        $resource = $this->findResource($uri);
        
        if (!$resource) {
            throw new \RuntimeException("Resource not found: $uri");
        }
        
        $this->logger->info('Resource read', ['uri' => $uri]);
        
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'uri'      => $resource->getUri(),
                'mimeType' => $resource->getMimeType(),
                'content'  => $resource->getContent(),
                'metadata' => $resource->getMetadata()
            ]
        ];
    }
    
    /**
     * Находит ресурс по URI
     * 
     * @param string $uri URI для поиска
     * 
     * @return ResourceInterface|null Найденный ресурс или null
     */
    private function findResource(string $uri): ?ResourceInterface
    {
        // Прямое совпадение
        if (isset($this->resources[$uri])) {
            return $this->resources[$uri];
        }
        
        // Поиск по паттерну
        foreach ($this->resources as $resource) {
            if ($resource->matchesUri($uri)) {
                return $resource;
            }
        }
        
        return null;
    }
    
    /**
     * Обрабатывает выполнение команды
     * 
     * @param string|null $id Идентификатор запроса
     * @param string $method Имя команды
     * @param array $params Параметры команды
     * 
     * @return array Результат выполнения команды
     * 
     * @throws \RuntimeException Если команда не найдена
     */
    private function handleCommand(?string $id, string $method, array $params): array
    {
        if (!isset($this->commands[$method])) {
            throw new \RuntimeException("Method not found: $method");
        }
        
        $command = $this->commands[$method];
        
        try {
            $result = $command->execute($params);
            
            $this->logger->info('Command executed', [
                'command' => $method,
                'params' => $params
            ]);
            
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $result
            ];
            
        } catch (ValidationException $e) {
            // Обработка ошибок валидации согласно MCP протоколу
            $this->logger->warning('Validation failed', [
                'command' => $method,
                'errors' => $e->getValidationErrors()
            ]);
            
            return $this->createValidationError($id, $e->getValidationErrors());
        }
    }
    
    /**
     * Создает ответ с ошибкой валидации
     * 
     * @param string|null $id Идентификатор запроса
     * @param array $validationErrors Массив ошибок валидации
     * 
     * @return array Ответ с ошибкой валидации
     */
    private function createValidationError(?string $id, array $validationErrors): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => -32602,
                'message' => 'Invalid parameters',
                'data' => [
                    'validation_errors' => $validationErrors
                ]
            ]
        ];
    }
    
    /**
     * Отправляет ошибку в формате JSON-RPC
     * 
     * @param string|null $id Идентификатор запроса
     * @param int $code Код ошибки
     * @param string $message Сообщение об ошибке
     * @param string|null $data Дополнительные данные об ошибке
     * 
     * @return void
     */
    private function sendError(?string $id, int $code, string $message, string $data = null): void
    {
        $error = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
        
        if ($data) {
            $error['error']['data'] = $data;
        }
        
        fwrite(STDOUT, json_encode($error) . "\n");
        $this->logger->error('Error response sent', [
            'code' => $code,
            'message' => $message,
            'data' => $data
        ]);
    }
}