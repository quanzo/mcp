# MCP (Model Context Protocol) Сервер на PHP

Полнофункциональная реализация MCP сервера на PHP 8.1, поддерживающая команды, ресурсы, авторизацию и валидацию входных данных.

## Возможности

- ✅ Поддержка команд (tools) через JSON-RPC 2.0
- ✅ Поддержка ресурсов (resources) для чтения статических данных
- ✅ Валидация входных параметров по JSON Schema
- ✅ Авторизация с помощью ключа
- ✅ PSR-совместимое логирование в файл
- ✅ Работа через stdio (стандартные потоки ввода/вывода)
- ✅ Веб-интерфейс для тестирования и управления
- ✅ Готовые примеры команд и ресурсов

## Требования

- PHP 8.1 или выше
- Composer для управления зависимостями

## Установка

1. Клонируйте репозиторий:
```bash
git clone <repository-url>
cd mcp-server
```

2. Установите зависимости:

```bash
composer install
```

3. Создайте необходимые директории:

```bash
mkdir -p logs config data
```

4. Настройте переменные окружения (опционально):

```bash
export MCP_AUTH_KEY="your-secret-key-here"
```

## Структура проекта

```
bin/
├── mcp_server.php                # Точка входа MCP сервера (stdio)
├── http_server.php               # HTTP гейтвей (встроенный PHP сервер)
├── http_server_amp.php           # HTTP гейтвей на Amp
└── test_client.php               # CLI-клиент для демонстрации/тестирования

src/
├── classes/                      # Классы проекта
│   ├── Server.php                # MCP сервер (stdio)
│   ├── MCPHttpServer.php         # HTTP гейтвей (sync)
│   ├── MCPHttpServerAmp.php      # HTTP гейтвей (Amp)
│   ├── commands/                 # Команды (tools)
│   ├── resources/                # Ресурсы
│   ├── validation/               # Валидация JSON Schema
│   ├── client/                   # MCPClient (stdio)
│   ├── dto/                      # DTO для JSON-RPC
│   ├── log/                      # Логирование
│   └── http/                     # HTTP-утилиты
├── interfaces/                   # Интерфейсы
├── helpers/                      # Хелперы (при необходимости)
├── traits/                       # Трейты (при необходимости)
└── enums/                        # Enum'ы (при необходимости)

docs/
└── mcp-nuances.md                # Тонкости MCP в этой реализации

tests/                            # Unit/Integration тесты
```

## Использование

### Запуск сервера

```bash
# Прямой запуск
php bin/mcp_server.php

# С указанием ключа авторизации
MCP_AUTH_KEY="my-key" php bin/mcp_server.php
```

### Тестирование с помощью клиента

```bash
php bin/test_client.php
```

### Примеры запросов

Получение списка команд:

```json
{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "mcp.listCommands",
    "params": {
        "auth": "your-secret-key"
    }
    
}
```

Выполнение команды echo:

```json
{
    "jsonrpc": "2.0",
    "id": 2,
    "method": "echo",
    "params": {
        "auth": "your-secret-key",
        "message": "Hello World"
    }
}
```

Получение списка ресурсов:

```json
{
    "jsonrpc": "2.0",
    "id": 3,
    "method": "mcp.listResources",
    "params": {
        "auth": "your-secret-key"
    }
}
```

Чтение ресурса:

```json
{
    "jsonrpc": "2.0",
    "id": 4,
    "method": "mcp.readResource",
    "params": {
        "auth": "your-secret-key",
        "uri": "file://logs/mcp-server.log"
    }
}
```

## Создание собственных команд

1. Создайте новый класс в пространстве имен quanzo\mcp\commands:

```php
namespace quanzo\mcp\commands;

use quanzo\mcp\commands\BaseCommand;
use quanzo\mcp\validation\ValidationException;

class MyCustomCommand extends BaseCommand
{
    public function __construct()
    {
        $this->name = 'my.command';
        $this->description = 'Моя пользовательская команда';
    }
    
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'param1' => [
                    'type' => 'string',
                    'description' => 'Параметр 1'
                ]
            ],
            'required' => ['param1']
        ];
    }
    
    protected function doExecute(array $params): array
    {
        // Логика команды
        return [
            'result' => 'Команда выполнена',
            'param' => $params['param1']
        ];
    }
}
```

2. Зарегистрируйте команду в сервере:

```php
use quanzo\mcp\commands\MyCustomCommand;

$server->registerCommand(new MyCustomCommand());
```

## Создание собственных ресурсов

Создайте новый класс ресурса:

```php
namespace quanzo\mcp\resources;

use quanzo\mcp\interfaces\ResourceInterface;

class MyResource implements ResourceInterface
{
    public function getUri(): string
    {
        return 'myresource://data';
    }
    
    public function getMimeType(): string
    {
        return 'application/json';
    }
    
    public function getContent(): string
    {
        return json_encode(['data' => 'Пример данных']);
    }
    
    public function getMetadata(): array
    {
        return ['type' => 'custom'];
    }
    
    public function matchesUri(string $uri): bool
    {
        return $uri === $this->getUri();
    }
}
```

2. Зарегистрируйте ресурс в сервере:

```php
use quanzo\mcp\resources\MyResource;

$server->registerResource(new MyResource());
```

## Настройка логирования

По умолчанию логи записываются в файл logs/mcp-server.log. Вы можете изменить уровень логирования:

```php
use quanzo\mcp\log\FileLogger;

$logger = new FileLogger(
    __DIR__ . '/logs/mcp-server.log',
    \Psr\Log\LogLevel::DEBUG // Уровень логирования
);
```

Доступные уровни: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY.

## Авторизация

Сервер поддерживает простую авторизацию по ключу. Ключ можно передать:

Через переменную окружения MCP_AUTH_KEY

Прямо в конструкторе сервера

В каждом запросе в параметре auth

```php
use quanzo\mcp\Server;

$server = new Server('my-secret-key', $logger);
```

## Валидация

Каждая команда должна определить схему входных параметров в формате JSON Schema. Сервер автоматически валидирует входные данные и возвращает детальные ошибки при несоответствии.

## Пример интеграции

### Использование в другом PHP проекте:

```php
require_once 'vendor/autoload.php';

use quanzo\mcp\Server;
use quanzo\mcp\log\FileLogger;

// Настройка логгера
$logger = new FileLogger('/var/log/mcp-server.log');

// Создание сервера
$server = new Server('your-secret-key', $logger);

// Регистрация кастомных команд
$server->registerCommand(new MyCustomCommand());

// Запуск сервера (в отдельном процессе)
$server->run();
```

## Использование через CLI:
```bash
# Запуск сервера в фоновом режиме
nohup php bin/mcp_server.php > /dev/null 2>&1 &

# Отправка запроса через curl (если реализован HTTP интерфейс)
curl -X POST http://localhost:8000/api/mcp/execute -H "Content-Type: application/json" -d '{"command": "echo", "params": {"message": "Hello"}}'
```

## Класс MCPClient

Для удобства тестирования и интеграции предоставлен класс MCPClient в пространстве имен quanzo\mcp\client. Он позволяет взаимодействовать с сервером через stdio:

```php
use quanzo\mcp\client\MCPClient;

$client = new MCPClient(__DIR__ . '/bin/mcp_server.php', 'your-secret-key');

// Получение списка команд
$commands = $client->listCommands();

// Выполнение команды
$result = $client->sendRequest('echo', ['message' => 'Hello']);
```
# Запуск серверов

```bash
# Простая версия (встроенный PHP сервер)
php bin/http_server.php -p 8080 -k my-secret-key
```

```bash
# Amp версия (высокая производительность)
php bin/http_server_amp.php 0.0.0.0 8080 my-secret-key
```

```bash
# Запуск в фоновом режиме
nohup php bin/http_server_amp.php 0.0.0.0 8080 > /var/log/mcp-http.log 2>&1 &
```

## Документация по нюансам MCP

См. `docs/mcp-nuances.md`.