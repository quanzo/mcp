<?php

declare(strict_types=1);

namespace Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use quanzo\mcp\classes\McpServer;
use quanzo\mcp\commands\EchoCommand;
use quanzo\mcp\classes\dto\mcp\ServerInfo;
use quanzo\mcp\classes\transport\StreamableHttpTransport;
use quanzo\mcp\helpers\JsonHelper;

/**
 * Класс StreamableHttpTransportTest
 *
 * Тестирует Streamable HTTP: initialize/session, notifications 202, Origin, auth, GET 405.
 */
class StreamableHttpTransportTest extends TestCase
{
    /**
     * Создаёт транспорт с echo tool
     *
     * @param string|null $bearer Bearer token
     * @param list<string> $origins Allowed origins
     *
     * @return StreamableHttpTransport
     */
    private function createTransport(?string $bearer = null, array $origins = []): StreamableHttpTransport
    {
        $server = new McpServer(new ServerInfo('http-test', '1.0.0'), new NullLogger());
        $server->registerCommand(new EchoCommand());

        return new StreamableHttpTransport($server, $bearer, $origins);
    }

    /**
     * Базовые заголовки клиента
     *
     * @param array<string, string> $extra Доп. заголовки
     *
     * @return array<string, string>
     */
    private function headers(array $extra = []): array
    {
        return array_merge([
            'accept' => 'application/json, text/event-stream',
            'content-type' => 'application/json',
        ], $extra);
    }

    /**
     * Тест: initialize выдаёт Mcp-Session-Id
     *
     * @return void
     */
    public function testInitializeSetsSessionId(): void
    {
        $transport = $this->createTransport();
        $body = JsonHelper::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 't', 'version' => '1'],
            ],
        ]);

        $result = $transport->handleHttpRequest('POST', '/mcp', $this->headers(), $body);

        self::assertSame(200, $result->getStatusCode());
        self::assertArrayHasKey('Mcp-Session-Id', $result->getHeaders());
        $payload = JsonHelper::decode($result->getBody(), true);
        self::assertSame('2025-03-26', $payload['result']['protocolVersion']);
    }

    /**
     * Тест: notification после initialize → 202
     *
     * @return void
     */
    public function testNotificationReturns202(): void
    {
        $transport = $this->createTransport();
        $init = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 't', 'version' => '1'],
                ],
            ])
        );
        $sessionId = $init->getHeaders()['Mcp-Session-Id'];

        $result = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(['mcp-session-id' => $sessionId]),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'method' => 'notifications/initialized',
            ])
        );

        self::assertSame(202, $result->getStatusCode());
        self::assertSame('', $result->getBody());
    }

    /**
     * Тест: tools/call через HTTP
     *
     * @return void
     */
    public function testToolsCallOverHttp(): void
    {
        $transport = $this->createTransport();
        $init = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 't', 'version' => '1'],
                ],
            ])
        );
        $sessionId = $init->getHeaders()['Mcp-Session-Id'];
        $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(['mcp-session-id' => $sessionId]),
            JsonHelper::encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])
        );

        $result = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(['mcp-session-id' => $sessionId]),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'echo',
                    'arguments' => ['message' => 'http'],
                ],
            ])
        );

        self::assertSame(200, $result->getStatusCode());
        $payload = JsonHelper::decode($result->getBody(), true);
        self::assertFalse($payload['result']['isError']);
        self::assertStringContainsString('http', $payload['result']['content'][0]['text']);
    }

    /**
     * Тест: запрос без session после initialize → 400
     *
     * @return void
     */
    public function testMissingSessionReturns400(): void
    {
        $transport = $this->createTransport();
        $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 't', 'version' => '1'],
                ],
            ])
        );

        $result = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/list',
            ])
        );

        self::assertSame(400, $result->getStatusCode());
    }

    /**
     * Тест: неизвестная сессия → 404
     *
     * @return void
     */
    public function testUnknownSessionReturns404(): void
    {
        $transport = $this->createTransport();
        $result = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(['mcp-session-id' => 'deadbeefdeadbeefdeadbeefdeadbeef']),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/list',
            ])
        );

        self::assertSame(404, $result->getStatusCode());
    }

    /**
     * Тест: GET /mcp → 405
     *
     * @return void
     */
    public function testGetReturns405(): void
    {
        $transport = $this->createTransport();
        $result = $transport->handleHttpRequest('GET', '/mcp', $this->headers(), '');
        self::assertSame(405, $result->getStatusCode());
    }

    /**
     * Тест: неверный Origin → 403
     *
     * @return void
     */
    public function testInvalidOriginRejected(): void
    {
        $transport = $this->createTransport();
        $result = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(['origin' => 'https://evil.example']),
            '{}'
        );
        self::assertSame(403, $result->getStatusCode());
    }

    /**
     * Тест: localhost Origin разрешён
     *
     * @return void
     */
    public function testLocalhostOriginAllowed(): void
    {
        $transport = $this->createTransport();
        $result = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(['origin' => 'http://127.0.0.1:3000']),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 't', 'version' => '1'],
                ],
            ])
        );
        self::assertSame(200, $result->getStatusCode());
    }

    /**
     * Тест: Bearer auth — отказ без токена и успех с токеном
     *
     * @return void
     */
    public function testBearerAuth(): void
    {
        $transport = $this->createTransport('secret-token');

        $denied = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(),
            '{}'
        );
        self::assertSame(401, $denied->getStatusCode());

        $ok = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(['authorization' => 'Bearer secret-token']),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 't', 'version' => '1'],
                ],
            ])
        );
        self::assertSame(200, $ok->getStatusCode());
    }

    /**
     * Тест: DELETE завершает сессию; повторный DELETE → 404
     *
     * @return void
     */
    public function testDeleteSession(): void
    {
        $transport = $this->createTransport();
        $init = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 't', 'version' => '1'],
                ],
            ])
        );
        $sessionId = $init->getHeaders()['Mcp-Session-Id'];

        $deleted = $transport->handleHttpRequest(
            'DELETE',
            '/mcp',
            $this->headers(['mcp-session-id' => $sessionId]),
            ''
        );
        self::assertSame(200, $deleted->getStatusCode());

        $again = $transport->handleHttpRequest(
            'DELETE',
            '/mcp',
            $this->headers(['mcp-session-id' => $sessionId]),
            ''
        );
        self::assertSame(404, $again->getStatusCode());
    }

    /**
     * Тест: невалидный JSON → 400 parse error
     *
     * @return void
     */
    public function testInvalidJsonReturns400(): void
    {
        $transport = $this->createTransport();
        $result = $transport->handleHttpRequest('POST', '/mcp', $this->headers(), '{not-json');
        self::assertSame(400, $result->getStatusCode());
        $payload = JsonHelper::decode($result->getBody(), true);
        self::assertSame(-32700, $payload['error']['code']);
    }

    /**
     * Тест: неизвестный path → 404
     *
     * @return void
     */
    public function testUnknownPathReturns404(): void
    {
        $transport = $this->createTransport();
        $result = $transport->handleHttpRequest('POST', '/api/commands', $this->headers(), '{}');
        self::assertSame(404, $result->getStatusCode());
    }

    /**
     * Тест resilience: Accept без application/json → 406
     *
     * @return void
     */
    public function testAcceptWithoutJsonReturns406(): void
    {
        $transport = $this->createTransport();
        $result = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            ['accept' => 'text/plain', 'content-type' => 'application/json'],
            '{}'
        );
        self::assertSame(406, $result->getStatusCode());
    }

    /**
     * Тест resilience: пустое POST body → 400, не 500
     *
     * @return void
     */
    public function testEmptyBodyReturns400(): void
    {
        $transport = $this->createTransport();
        $result = $transport->handleHttpRequest('POST', '/mcp', $this->headers(), '');
        self::assertSame(400, $result->getStatusCode());
        $payload = JsonHelper::decode($result->getBody(), true);
        self::assertSame(-32700, $payload['error']['code']);
    }

    /**
     * Тест resilience: неверный Bearer → 401
     *
     * @return void
     */
    public function testWrongBearerReturns401(): void
    {
        $transport = $this->createTransport('secret-token');
        $result = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(['authorization' => 'Bearer wrong']),
            '{}'
        );
        self::assertSame(401, $result->getStatusCode());
    }

    /**
     * Тест resilience: DELETE без Mcp-Session-Id → 400
     *
     * @return void
     */
    public function testDeleteWithoutSessionReturns400(): void
    {
        $transport = $this->createTransport();
        $result = $transport->handleHttpRequest('DELETE', '/mcp', $this->headers(), '');
        self::assertSame(400, $result->getStatusCode());
    }

    /**
     * Тест: batch initialize + tools/list в одном POST
     *
     * @return void
     */
    public function testBatchInitializeAndToolsList(): void
    {
        $transport = $this->createTransport();
        $body = JsonHelper::encode([
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 't', 'version' => '1'],
                ],
            ],
            [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/list',
            ],
        ]);

        $result = $transport->handleHttpRequest('POST', '/mcp', $this->headers(), $body);
        self::assertSame(200, $result->getStatusCode());
        self::assertArrayHasKey('Mcp-Session-Id', $result->getHeaders());

        $payload = JsonHelper::decode($result->getBody(), true);
        self::assertIsArray($payload);
        self::assertCount(2, $payload);
        self::assertArrayHasKey('result', $payload[0]);
        self::assertArrayHasKey('tools', $payload[1]['result']);
    }

    /**
     * Тест: после DELETE сессии tools/list с тем же id → 404
     *
     * @return void
     */
    public function testToolsListAfterDeleteReturns404(): void
    {
        $transport = $this->createTransport();
        $init = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 't', 'version' => '1'],
                ],
            ])
        );
        $sessionId = $init->getHeaders()['Mcp-Session-Id'];

        $transport->handleHttpRequest(
            'DELETE',
            '/mcp',
            $this->headers(['mcp-session-id' => $sessionId]),
            ''
        );

        $result = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(['mcp-session-id' => $sessionId]),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/list',
            ])
        );
        self::assertSame(404, $result->getStatusCode());
    }

    /**
     * Тест resilience: битые args → HTTP 200 + JSON-RPC -32602 (не 500)
     *
     * @return void
     */
    public function testInvalidToolArgsReturnJsonRpcErrorNotHttp500(): void
    {
        $transport = $this->createTransport();
        $init = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 't', 'version' => '1'],
                ],
            ])
        );
        $sessionId = $init->getHeaders()['Mcp-Session-Id'];
        $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(['mcp-session-id' => $sessionId]),
            JsonHelper::encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])
        );

        $result = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(['mcp-session-id' => $sessionId]),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 3,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'echo',
                    'arguments' => [],
                ],
            ])
        );

        self::assertSame(200, $result->getStatusCode());
        $payload = JsonHelper::decode($result->getBody(), true);
        self::assertSame(-32602, $payload['error']['code']);
    }

    /**
     * Тест: пустой Accept → 406
     *
     * @return void
     */
    public function testEmptyAcceptReturns406(): void
    {
        $transport = $this->createTransport();
        $result = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            ['content-type' => 'application/json'],
            '{}'
        );
        self::assertSame(406, $result->getStatusCode());
    }

    /**
     * Тест: Accept только JSON без event-stream → 406
     *
     * @return void
     */
    public function testAcceptJsonOnlyReturns406(): void
    {
        $transport = $this->createTransport();
        $result = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            ['accept' => 'application/json', 'content-type' => 'application/json'],
            '{}'
        );
        self::assertSame(406, $result->getStatusCode());
    }

    /**
     * Тест: Accept wildcard (* / *) допускается
     *
     * @return void
     */
    public function testAcceptStarStarAllowed(): void
    {
        $transport = $this->createTransport();
        $result = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            ['accept' => '*/*', 'content-type' => 'application/json'],
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 't', 'version' => '1'],
                ],
            ])
        );
        self::assertSame(200, $result->getStatusCode());
    }

    /**
     * Тест: отсутствие MCP-Protocol-Version допустимо (default)
     *
     * @return void
     */
    public function testMissingProtocolVersionHeaderAllowed(): void
    {
        $transport = $this->createTransport();
        $result = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 't', 'version' => '1'],
                ],
            ])
        );
        self::assertSame(200, $result->getStatusCode());
    }

    /**
     * Тест: поддерживаемая MCP-Protocol-Version → OK
     *
     * @return void
     */
    public function testSupportedProtocolVersionHeaderOk(): void
    {
        $transport = $this->createTransport();
        $result = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(['mcp-protocol-version' => '2025-03-26']),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 't', 'version' => '1'],
                ],
            ])
        );
        self::assertSame(200, $result->getStatusCode());
    }

    /**
     * Тест: неподдерживаемая MCP-Protocol-Version → 400
     *
     * @return void
     */
    public function testUnsupportedProtocolVersionReturns400(): void
    {
        $transport = $this->createTransport();
        $result = $transport->handleHttpRequest(
            'POST',
            '/mcp',
            $this->headers(['mcp-protocol-version' => '1999-01-01']),
            JsonHelper::encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 't', 'version' => '1'],
                ],
            ])
        );
        self::assertSame(400, $result->getStatusCode());
    }

    /**
     * Тест: DELETE с неподдерживаемой версией → 400
     *
     * @return void
     */
    public function testDeleteUnsupportedProtocolVersionReturns400(): void
    {
        $transport = $this->createTransport();
        $result = $transport->handleHttpRequest(
            'DELETE',
            '/mcp',
            $this->headers([
                'mcp-session-id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'mcp-protocol-version' => '0.0.0',
            ]),
            ''
        );
        self::assertSame(400, $result->getStatusCode());
    }
}
