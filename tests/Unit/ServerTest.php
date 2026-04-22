<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use quanzo\mcp\classes\Server;
use quanzo\mcp\classes\commands\BaseCommand;
use quanzo\mcp\classes\validation\ValidationException;
use quanzo\mcp\interfaces\ResourceInterface;

/**
 * Класс ServerTest
 *
 * Тестирует внутреннюю логику MCP сервера:
 * - регистрацию и список команд/ресурсов;
 * - успешное выполнение команды;
 * - обработку ошибок валидации и отсутствующей команды;
 * - авторизацию и ошибки авторизации;
 * - чтение ресурсов и обработку ошибок при работе с ресурсами.
 */
class ServerTest extends TestCase
{
    /**
     * Создает экземпляр сервера без авторизации
     *
     * @return Server
     */
    private function createServer(): Server
    {
        return new Server(null, new NullLogger());
    }

    /**
     * Тестирует регистрацию команды и вывод в mcp.listCommands
     *
     * @return void
     */
    public function testListCommandsReturnsRegisteredCommand(): void
    {
        $server = $this->createServer();

        $command = new class () extends BaseCommand {
            public function __construct()
            {
                $this->name = 'test.command';
                $this->description = 'Test command';
            }

            public function getInputSchema(): array
            {
                return [
                    'type' => 'object',
                    'properties' => ['foo' => ['type' => 'string']],
                    'required' => ['foo'],
                ];
            }

            protected function doExecute(array $params): array
            {
                return ['ok' => true];
            }
        };

        $server->registerCommand($command);

        $listResponse = $this->invokeHandleRequest($server, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'mcp.listCommands',
        ]);

        self::assertArrayHasKey('result', $listResponse);
        self::assertCount(1, $listResponse['result']['commands']);
        self::assertSame('test.command', $listResponse['result']['commands'][0]['name']);
    }

    /**
     * Тестирует успешное выполнение команды через handleRequest
     *
     * @return void
     */
    public function testHandleCommandSuccess(): void
    {
        $server = $this->createServer();

        $command = new class () extends BaseCommand {
            public function __construct()
            {
                $this->name = 'echo.simple';
                $this->description = 'Simple echo';
            }

            public function getInputSchema(): array
            {
                return [
                    'type' => 'object',
                    'properties' => ['value' => ['type' => 'string']],
                    'required' => ['value'],
                ];
            }

            protected function doExecute(array $params): array
            {
                return ['echo' => $params['value']];
            }
        };

        $server->registerCommand($command);

        $response = $this->invokeHandleRequest($server, [
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'echo.simple',
            'params' => ['value' => 'test'],
        ]);

        self::assertArrayHasKey('result', $response);
        self::assertSame('test', $response['result']['echo']);
    }

    /**
     * Тестирует обработку ошибок валидации команды
     *
     * @return void
     */
    public function testHandleCommandValidationError(): void
    {
        $server = $this->createServer();

        $command = new class () extends BaseCommand {
            public function __construct()
            {
                $this->name = 'validate.only';
                $this->description = 'Always fails validation';
            }

            protected function doExecute(array $params): array
            {
                throw new ValidationException([
                    ['property' => 'field', 'message' => 'invalid'],
                ]);
            }
        };

        $server->registerCommand($command);

        $response = $this->invokeHandleRequest($server, [
            'jsonrpc' => '2.0',
            'id' => 11,
            'method' => 'validate.only',
            'params' => [],
        ]);

        self::assertArrayHasKey('error', $response);
        self::assertSame(-32602, $response['error']['code']);
        self::assertArrayHasKey('validation_errors', $response['error']['data']);
    }

    /**
     * Тестирует ошибку для несуществующей команды
     *
     * @return void
     */
    public function testUnknownMethodThrowsRuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);

        $server = $this->createServer();
        $this->invokeHandleRequest($server, [
            'jsonrpc' => '2.0',
            'id' => 12,
            'method' => 'unknown.method',
        ]);
    }

    /**
     * Тестирует успешную авторизацию и передачу auth ключа
     *
     * @return void
     */
    public function testAuthorizationSuccess(): void
    {
        $authKey = 'secret-key';
        $server = new Server($authKey, new NullLogger());

        $command = new class () extends BaseCommand {
            public array $lastParams = [];

            public function __construct()
            {
                $this->name = 'auth.echo';
                $this->description = 'Auth echo';
            }

            protected function doExecute(array $params): array
            {
                $this->lastParams = $params;

                return ['ok' => true];
            }
        };

        $server->registerCommand($command);

        $response = $this->invokeHandleRequest($server, [
            'jsonrpc' => '2.0',
            'id' => 13,
            'method' => 'auth.echo',
            'params' => [
                'auth' => $authKey,
                'foo' => 'bar',
            ],
        ]);

        self::assertArrayHasKey('result', $response);
        self::assertArrayHasKey('ok', $response['result']);
        self::assertSame(
            ['foo' => 'bar'],
            $command->lastParams,
            'auth param must be stripped before command execution'
        );
    }

    /**
     * Тестирует ошибку авторизации при неверном ключе
     *
     * @return void
     */
    public function testAuthorizationFailureThrowsRuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);

        $authKey = 'secret-key';
        $server = new Server($authKey, new NullLogger());

        $this->invokeHandleRequest($server, [
            'jsonrpc' => '2.0',
            'id' => 14,
            'method' => 'any.method',
            'params' => [
                'auth' => 'wrong-key',
            ],
        ]);
    }

    /**
     * Тестирует чтение ресурса и ошибки при некорректном запросе
     *
     * @return void
     */
    public function testReadResourceSuccessAndErrors(): void
    {
        $server = $this->createServer();

        $resource = new class () implements ResourceInterface {
            public function getUri(): string
            {
                return 'test://resource';
            }

            public function getMimeType(): string
            {
                return 'text/plain';
            }

            public function getContent(?string $requestedUri = null): string
            {
                return 'content';
            }

            public function getMetadata(): array
            {
                return ['key' => 'value'];
            }

            public function matchesUri(string $uri): bool
            {
                return $uri === $this->getUri();
            }
        };

        $server->registerResource($resource);

        $successResponse = $this->invokeHandleRequest($server, [
            'jsonrpc' => '2.0',
            'id' => 15,
            'method' => 'mcp.readResource',
            'params' => ['uri' => 'test://resource'],
        ]);

        self::assertArrayHasKey('result', $successResponse);
        self::assertSame('content', $successResponse['result']['content']);

        $this->expectException(\InvalidArgumentException::class);

        $this->invokeHandleRequest($server, [
            'jsonrpc' => '2.0',
            'id' => 16,
            'method' => 'mcp.readResource',
            'params' => [],
        ]);
    }

    public function testReadResourceMatchesUriPatternAndHandlesMissingFile(): void
    {
        $server = $this->createServer();

        $tmpDir = sys_get_temp_dir() . '/mcp-resource-test-' . uniqid('', true);
        if (!mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            self::fail('Failed to create temp dir for resource test');
        }

        $filename = 'test.txt';
        $content = 'hello';
        file_put_contents($tmpDir . '/' . $filename, $content);

        $resource = new \quanzo\mcp\classes\resources\FileResource(
            'file://logs/*',
            'text/plain',
            $tmpDir
        );
        $server->registerResource($resource);

        $patternMatchResponse = $this->invokeHandleRequest($server, [
            'jsonrpc' => '2.0',
            'id' => 17,
            'method' => 'mcp.readResource',
            'params' => ['uri' => 'file://logs/' . $filename],
        ]);

        self::assertArrayHasKey('result', $patternMatchResponse);
        self::assertSame($content, $patternMatchResponse['result']['content']);

        $this->expectException(\RuntimeException::class);

        $this->invokeHandleRequest($server, [
            'jsonrpc' => '2.0',
            'id' => 18,
            'method' => 'mcp.readResource',
            'params' => ['uri' => 'file://logs/missing.txt'],
        ]);
    }

    /**
     * Вспомогательный метод для вызова приватного handleRequest через Reflection
     *
     * @param Server $server Экземпляр сервера
     * @param array $request Запрос JSON-RPC
     *
     * @return array Ответ сервера
     */
    private function invokeHandleRequest(Server $server, array $request): array
    {
        $reflection = new \ReflectionClass($server);
        $method = $reflection->getMethod('handleRequest');

        /** @var array $response */
        $response = $method->invoke($server, $request);

        return $response;
    }
}
