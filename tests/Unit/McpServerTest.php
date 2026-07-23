<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use quanzo\mcp\classes\McpServer;
use quanzo\mcp\commands\BaseCommand;
use quanzo\mcp\commands\CalculateCommand;
use quanzo\mcp\classes\dto\mcp\ServerInfo;
use quanzo\mcp\interfaces\ResourceInterface;

/**
 * Класс McpServerTest
 *
 * Тестирует стандартное MCP-ядро: lifecycle, tools/*, resources/*, ошибки протокола.
 * Включает граничные и заведомо неверные данные.
 */
class McpServerTest extends TestCase
{
    /**
     * Создаёт сервер без tools/resources
     *
     * @return McpServer
     */
    private function createServer(): McpServer
    {
        return new McpServer(new ServerInfo('test-mcp', '1.0.0'), new NullLogger());
    }

    /**
     * Анонимная команда для тестов
     *
     * @param string $name Имя
     * @param callable(array): array $handler Обработчик
     *
     * @return BaseCommand
     */
    private function makeCommand(string $name, callable $handler): BaseCommand
    {
        return new class ($name, $handler) extends BaseCommand {
            /** @var callable(array): array */
            private $handler;

            public function __construct(string $name, callable $handler)
            {
                $this->name = $name;
                $this->description = 'test tool';
                $this->handler = $handler;
            }

            public function getInputSchema(): array
            {
                return [
                    'type' => 'object',
                    'properties' => [
                        'value' => ['type' => 'string'],
                    ],
                    'required' => ['value'],
                ];
            }

            protected function doExecute(array $params): array
            {
                return ($this->handler)($params);
            }
        };
    }

    /**
     * Анонимный ресурс для тестов
     *
     * @return ResourceInterface
     */
    private function makeResource(): ResourceInterface
    {
        return new class () implements ResourceInterface {
            public function getUri(): string
            {
                return 'test://item';
            }

            public function getName(): string
            {
                return 'test_item';
            }

            public function getDescription(): ?string
            {
                return 'Test resource';
            }

            public function getMimeType(): string
            {
                return 'text/plain';
            }

            public function getContent(?string $requestedUri = null): string
            {
                return 'hello';
            }

            public function matchesUri(string $uri): bool
            {
                return $uri === $this->getUri();
            }
        };
    }

    /**
     * Выполняет initialize + notifications/initialized
     *
     * @param McpServer $server Сервер
     *
     * @return void
     */
    private function bootstrap(McpServer $server): void
    {
        $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
        ]);
        $server->handleMessage([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]);
    }

    /**
     * Тест: initialize возвращает protocolVersion и serverInfo
     *
     * @return void
     */
    public function testInitializeReturnsServerInfo(): void
    {
        $server = $this->createServer();
        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => [],
                'clientInfo' => ['name' => 'c', 'version' => '1'],
            ],
        ]);

        self::assertNotNull($response);
        self::assertSame('2025-03-26', $response['result']['protocolVersion']);
        self::assertSame('test-mcp', $response['result']['serverInfo']['name']);
        self::assertIsObject($response['result']['capabilities']);
    }

    /**
     * Тест: notifications/initialized не возвращает ответ
     *
     * @return void
     */
    public function testInitializedNotificationReturnsNull(): void
    {
        $server = $this->createServer();
        $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => [],
                'clientInfo' => ['name' => 'c', 'version' => '1'],
            ],
        ]);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]);

        self::assertNull($response);
        self::assertTrue($server->isInitializeDone());
    }

    /**
     * Тест: до initialize tools/list запрещён
     *
     * @return void
     */
    public function testToolsListBeforeInitializeFails(): void
    {
        $server = $this->createServer();
        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ]);

        self::assertSame(-32002, $response['error']['code']);
    }

    /**
     * Тест: ping до initialize разрешён
     *
     * @return void
     */
    public function testPingBeforeInitializeAllowed(): void
    {
        $server = $this->createServer();
        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'ping',
        ]);

        self::assertArrayHasKey('result', $response);
        self::assertArrayNotHasKey('error', $response);
    }

    /**
     * Тест: tools/list возвращает зарегистрированный tool
     *
     * @return void
     */
    public function testToolsListReturnsRegisteredTool(): void
    {
        $server = $this->createServer();
        $server->registerCommand($this->makeCommand('echo_test', static fn (array $p): array => $p));
        $this->bootstrap($server);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/list',
        ]);

        self::assertCount(1, $response['result']['tools']);
        self::assertSame('echo_test', $response['result']['tools'][0]['name']);
        self::assertArrayHasKey('inputSchema', $response['result']['tools'][0]);
    }

    /**
     * Тест: tools/call успех с content text
     *
     * @return void
     */
    public function testToolsCallSuccessWrapsContent(): void
    {
        $server = $this->createServer();
        $server->registerCommand(
            $this->makeCommand('echo_test', static fn (array $p): array => ['echo' => $p['value']])
        );
        $this->bootstrap($server);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'echo_test',
                'arguments' => ['value' => 'hi'],
            ],
        ]);

        self::assertFalse($response['result']['isError']);
        self::assertSame('text', $response['result']['content'][0]['type']);
        self::assertStringContainsString('hi', $response['result']['content'][0]['text']);
    }

    /**
     * Тест: tools/call без name — invalid params
     *
     * @return void
     */
    public function testToolsCallMissingName(): void
    {
        $server = $this->createServer();
        $this->bootstrap($server);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => ['arguments' => []],
        ]);

        self::assertSame(-32602, $response['error']['code']);
    }

    /**
     * Тест: tools/call неизвестный tool
     *
     * @return void
     */
    public function testToolsCallUnknownTool(): void
    {
        $server = $this->createServer();
        $this->bootstrap($server);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => ['name' => 'no_such_tool', 'arguments' => []],
        ]);

        self::assertSame(-32602, $response['error']['code']);
        self::assertStringContainsString('Unknown tool', $response['error']['message']);
    }

    /**
     * Тест: tools/call с невалидными аргументами (граничный/неверный набор)
     *
     * @return void
     */
    public function testToolsCallInvalidArguments(): void
    {
        $server = $this->createServer();
        $server->registerCommand($this->makeCommand('echo_test', static fn (array $p): array => $p));
        $this->bootstrap($server);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'tools/call',
            'params' => [
                'name' => 'echo_test',
                'arguments' => [],
            ],
        ]);

        self::assertSame(-32602, $response['error']['code']);
        self::assertArrayHasKey('validation_errors', $response['error']['data']);
    }

    /**
     * Тест: неизвестный method → -32601
     *
     * @return void
     */
    public function testUnknownMethodReturnsMethodNotFound(): void
    {
        $server = $this->createServer();
        $this->bootstrap($server);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 'abc',
            'method' => 'foo/bar',
        ]);

        self::assertSame(-32601, $response['error']['code']);
        self::assertSame('abc', $response['id']);
    }

    /**
     * Тест: resources/list и resources/read
     *
     * @return void
     */
    public function testResourcesListAndRead(): void
    {
        $server = $this->createServer();
        $server->registerResource($this->makeResource());
        $this->bootstrap($server);

        $list = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'resources/list',
        ]);
        self::assertSame('test://item', $list['result']['resources'][0]['uri']);
        self::assertSame('test_item', $list['result']['resources'][0]['name']);

        $read = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 9,
            'method' => 'resources/read',
            'params' => ['uri' => 'test://item'],
        ]);
        self::assertSame('hello', $read['result']['contents'][0]['text']);
        self::assertSame('test://item', $read['result']['contents'][0]['uri']);
    }

    /**
     * Тест: resources/read без uri и несуществующий uri
     *
     * @return void
     */
    public function testResourcesReadInvalidCases(): void
    {
        $server = $this->createServer();
        $this->bootstrap($server);

        $missingUri = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'resources/read',
            'params' => [],
        ]);
        self::assertSame(-32602, $missingUri['error']['code']);

        $notFound = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 11,
            'method' => 'resources/read',
            'params' => ['uri' => 'missing://x'],
        ]);
        self::assertSame(-32002, $notFound['error']['code']);
    }

    /**
     * Тест: id может быть int; пустой method → Invalid Request
     *
     * @return void
     */
    public function testIdIntAndEmptyMethod(): void
    {
        $server = $this->createServer();
        $this->bootstrap($server);

        $ping = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 42,
            'method' => 'ping',
        ]);
        self::assertSame(42, $ping['id']);

        $empty = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 43,
            'method' => '',
        ]);
        self::assertSame(-32600, $empty['error']['code']);
    }

    /**
     * Тест: createSessionInstance копирует tools
     *
     * @return void
     */
    public function testCreateSessionInstanceSharesTools(): void
    {
        $server = $this->createServer();
        $server->registerCommand($this->makeCommand('echo_test', static fn (array $p): array => $p));
        $session = $server->createSessionInstance();
        $this->bootstrap($session);

        $response = $session->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);
        self::assertCount(1, $response['result']['tools']);
        self::assertFalse($server->isInitializeDone());
    }

    /**
     * Тест resilience: divide-by-zero через tools/call → isError, без падения
     *
     * @return void
     */
    public function testToolsCallDivideByZeroReturnsIsError(): void
    {
        $server = $this->createServer();
        $server->registerCommand(new CalculateCommand());
        $this->bootstrap($server);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 50,
            'method' => 'tools/call',
            'params' => [
                'name' => 'calculate',
                'arguments' => ['operation' => 'divide', 'a' => 10, 'b' => 0],
            ],
        ]);

        self::assertArrayHasKey('result', $response);
        self::assertTrue($response['result']['isError']);
        self::assertStringContainsString('ноль', $response['result']['content'][0]['text']);
    }

    /**
     * Тест resilience: arguments не object → -32602
     *
     * @return void
     */
    public function testToolsCallArgumentsMustBeObject(): void
    {
        $server = $this->createServer();
        $server->registerCommand($this->makeCommand('echo_test', static fn (array $p): array => $p));
        $this->bootstrap($server);

        foreach (['string-args', 123, null] as $i => $badArgs) {
            $response = $server->handleMessage([
                'jsonrpc' => '2.0',
                'id' => 60 + $i,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'echo_test',
                    'arguments' => $badArgs,
                ],
            ]);
            self::assertSame(-32602, $response['error']['code'], 'Failed for args type: ' . gettype($badArgs));
        }
    }

    /**
     * Тест resilience: name не string / пустой → -32602
     *
     * @return void
     */
    public function testToolsCallInvalidNameTypes(): void
    {
        $server = $this->createServer();
        $this->bootstrap($server);

        $cases = [
            ['name' => 42],
            ['name' => ['x']],
            ['name' => ''],
            [],
        ];

        foreach ($cases as $i => $params) {
            $response = $server->handleMessage([
                'jsonrpc' => '2.0',
                'id' => 70 + $i,
                'method' => 'tools/call',
                'params' => $params,
            ]);
            self::assertSame(-32602, $response['error']['code']);
        }
    }

    /**
     * Тест resilience: неизвестный protocolVersion → fallback на дефолт
     *
     * @return void
     */
    public function testInitializeUnknownProtocolVersionFallsBack(): void
    {
        $server = $this->createServer();
        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 80,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '99.99.99',
                'capabilities' => [],
                'clientInfo' => ['name' => 'c', 'version' => '1'],
            ],
        ]);

        self::assertSame(McpServer::DEFAULT_PROTOCOL_VERSION, $response['result']['protocolVersion']);
        self::assertArrayNotHasKey('error', $response);
    }

    /**
     * Тест resilience: initialize с пустыми params → успех
     *
     * @return void
     */
    public function testInitializeWithEmptyParamsSucceeds(): void
    {
        $server = $this->createServer();
        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 81,
            'method' => 'initialize',
            'params' => [],
        ]);

        self::assertArrayHasKey('result', $response);
        self::assertSame(McpServer::DEFAULT_PROTOCOL_VERSION, $response['result']['protocolVersion']);
    }

    /**
     * Тест resilience: id явно null в запросе
     *
     * @return void
     */
    public function testRequestWithNullId(): void
    {
        $server = $this->createServer();
        $this->bootstrap($server);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => null,
            'method' => 'ping',
        ]);

        self::assertNull($response['id']);
        self::assertArrayHasKey('result', $response);
    }

    /**
     * Тест resilience: params скаляр нормализуется, сервер не падает
     *
     * @return void
     */
    public function testScalarParamsDoNotCrash(): void
    {
        $server = $this->createServer();
        $this->bootstrap($server);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 82,
            'method' => 'ping',
            'params' => 'not-an-object',
        ]);

        self::assertArrayHasKey('result', $response);
        self::assertArrayNotHasKey('error', $response);
    }

    /**
     * Тест resilience: неизвестная notification → null
     *
     * @return void
     */
    public function testUnknownNotificationReturnsNull(): void
    {
        $server = $this->createServer();
        $this->bootstrap($server);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'method' => 'notifications/something_unknown',
        ]);

        self::assertNull($response);
    }

    /**
     * Тест resilience: getContent кидает → JSON-RPC -32603, не uncaught
     *
     * @return void
     */
    public function testResourceContentThrowBecomesInternalError(): void
    {
        $server = $this->createServer();
        $server->registerResource(new class () implements ResourceInterface {
            public function getUri(): string
            {
                return 'fail://x';
            }

            public function getName(): string
            {
                return 'failing';
            }

            public function getDescription(): ?string
            {
                return null;
            }

            public function getMimeType(): string
            {
                return 'text/plain';
            }

            public function getContent(?string $requestedUri = null): string
            {
                throw new \RuntimeException('cannot read');
            }

            public function matchesUri(string $uri): bool
            {
                return $uri === $this->getUri();
            }
        });
        $this->bootstrap($server);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 83,
            'method' => 'resources/read',
            'params' => ['uri' => 'fail://x'],
        ]);

        self::assertSame(-32603, $response['error']['code']);
        self::assertStringContainsString('cannot read', (string) $response['error']['data']);
    }

    /**
     * Тест resilience: после ошибки валидации следующий валидный call успешен
     *
     * @return void
     */
    public function testRecoveryAfterValidationError(): void
    {
        $server = $this->createServer();
        $server->registerCommand($this->makeCommand('echo_test', static fn (array $p): array => ['v' => $p['value']]));
        $this->bootstrap($server);

        $bad = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 84,
            'method' => 'tools/call',
            'params' => ['name' => 'echo_test', 'arguments' => []],
        ]);
        self::assertSame(-32602, $bad['error']['code']);

        $good = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 85,
            'method' => 'tools/call',
            'params' => ['name' => 'echo_test', 'arguments' => ['value' => 'ok']],
        ]);
        self::assertFalse($good['result']['isError']);
        self::assertStringContainsString('ok', $good['result']['content'][0]['text']);
    }
}
