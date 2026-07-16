<?php

declare(strict_types=1);

namespace quanzo\mcp\classes\transport;

/**
 * Класс StreamableHttpTransport
 *
 * Стандартный MCP Streamable HTTP транспорт: единый endpoint /mcp (POST/GET/DELETE).
 * POST отвечает application/json (без обязательного SSE). GET возвращает 405.
 *
 * Пример использования:
 *   $transport = new StreamableHttpTransport($mcpServer);
 *   $result = $transport->handleHttpRequest('POST', '/mcp', $headers, $body);
 */
class StreamableHttpTransport
{
    /**
     * Путь MCP endpoint
     */
    public const MCP_PATH = '/mcp';

    /**
     * Хранилище сессий
     *
     * @var McpSessionStore
     */
    private McpSessionStore $sessions;

    /**
     * Опциональный Bearer-токен (null = без auth)
     *
     * @var string|null
     */
    private ?string $bearerToken;

    /**
     * Разрешённые Origin (пустой список = отсутствие Origin или localhost)
     *
     * @var list<string>
     */
    private array $allowedOrigins;

    /**
     * Конструктор StreamableHttpTransport
     *
     * @param \quanzo\mcp\classes\McpServer $templateServer Шаблон с tools/resources
     * @param string|null $bearerToken Опциональный Bearer token
     * @param list<string> $allowedOrigins Разрешённые Origin
     */
    public function __construct(
        \quanzo\mcp\classes\McpServer $templateServer,
        ?string $bearerToken = null,
        array $allowedOrigins = []
    ) {
        $this->sessions = new McpSessionStore($templateServer);
        $this->bearerToken = $bearerToken;
        $this->allowedOrigins = $allowedOrigins;
    }

    /**
     * Обрабатывает один HTTP-запрос
     *
     * Не бросает наружу на мусорном вводе: возвращает HttpTransportResult
     * с HTTP 4xx или JSON-RPC error в теле при статусе 200.
     *
     * @param string $method HTTP method
     * @param string $path URI path
     * @param array<string, string> $headers Заголовки (lowercase keys)
     * @param string $body Тело запроса
     *
     * @return HttpTransportResult
     */
    public function handleHttpRequest(
        string $method,
        string $path,
        array $headers,
        string $body
    ): HttpTransportResult {
        $parsedPath = parse_url($path, PHP_URL_PATH);
        $path = is_string($parsedPath) ? $parsedPath : $path;
        $path = rtrim($path, '/') ?: '/';

        if ($path === '/' || $path === '/health') {
            return HttpTransportResult::json(200, [
                'status' => 'ok',
                'mcp_endpoint' => self::MCP_PATH,
                'transport' => 'streamable-http',
            ]);
        }

        if ($path !== self::MCP_PATH) {
            return HttpTransportResult::json(404, ['error' => 'Not Found']);
        }

        if (!$this->isOriginAllowed($headers)) {
            return HttpTransportResult::text(403, 'Forbidden: invalid Origin');
        }

        if (!$this->isAuthorized($headers)) {
            return HttpTransportResult::text(401, 'Unauthorized');
        }

        $method = strtoupper($method);

        if ($method === 'OPTIONS') {
            return HttpTransportResult::empty(204, $this->corsHeaders());
        }

        if ($method === 'GET') {
            $headers = array_merge($this->corsHeaders(), ['Allow' => 'POST, DELETE, OPTIONS']);

            return HttpTransportResult::empty(405, $headers);
        }

        if ($method === 'DELETE') {
            return $this->handleDelete($headers);
        }

        if ($method === 'POST') {
            return $this->handlePost($headers, $body);
        }

        return HttpTransportResult::empty(405, array_merge($this->corsHeaders(), ['Allow' => 'POST, DELETE, OPTIONS']));
    }

    /**
     * Обрабатывает DELETE (завершение сессии)
     *
     * @param array<string, string> $headers Заголовки
     *
     * @return HttpTransportResult
     */
    private function handleDelete(array $headers): HttpTransportResult
    {
        $sessionId = $this->getSessionId($headers);
        if ($sessionId === null || $sessionId === '') {
            return HttpTransportResult::text(400, 'Missing Mcp-Session-Id', $this->corsHeaders());
        }

        if (!$this->sessions->delete($sessionId)) {
            return HttpTransportResult::text(404, 'Session not found', $this->corsHeaders());
        }

        return HttpTransportResult::empty(200, $this->corsHeaders());
    }

    /**
     * Обрабатывает POST с JSON-RPC
     *
     * @param array<string, string> $headers Заголовки
     * @param string $body Тело
     *
     * @return HttpTransportResult
     */
    private function handlePost(array $headers, string $body): HttpTransportResult
    {
        $accept = $headers['accept'] ?? '';
        if ($accept !== '' && !$this->acceptsJson($accept)) {
            return HttpTransportResult::text(406, 'Not Acceptable: application/json required', $this->corsHeaders());
        }

        try {
            $decoded = \quanzo\mcp\helpers\JsonHelper::decode($body, true);
        } catch (\JsonException $e) {
            return HttpTransportResult::json(
                400,
                [
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => [
                        'code' => -32700,
                        'message' => 'Parse error',
                        'data' => $e->getMessage(),
                    ],
                ],
                $this->corsHeaders()
            );
        }

        if (!is_array($decoded)) {
            return HttpTransportResult::json(
                400,
                [
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => [
                        'code' => -32700,
                        'message' => 'Parse error',
                        'data' => 'Expected JSON object or batch array',
                    ],
                ],
                $this->corsHeaders()
            );
        }

        if ($this->isJsonRpcBatch($decoded)) {
            /** @var list<array<string, mixed>> $decoded */
            return $this->handleBatch($headers, $decoded);
        }

        /** @var array<string, mixed> $decoded */
        return $this->handleSingleMessage($headers, $decoded);
    }

    /**
     * Обрабатывает одно JSON-RPC сообщение
     *
     * @param array<string, string> $headers Заголовки
     * @param array<string, mixed> $message Сообщение
     *
     * @return HttpTransportResult
     */
    private function handleSingleMessage(array $headers, array $message): HttpTransportResult
    {
        $method = isset($message['method']) && is_string($message['method']) ? $message['method'] : '';
        $hasId = array_key_exists('id', $message);

        if (!isset($message['method']) && (isset($message['result']) || isset($message['error']))) {
            return HttpTransportResult::empty(202, $this->corsHeaders());
        }

        $sessionId = $this->getSessionId($headers);
        $extraHeaders = $this->corsHeaders();

        if ($method === 'initialize') {
            // Новая сессия на initialize (или reuse, если клиент прислал известный id)
            if ($sessionId !== null && $this->sessions->get($sessionId) !== null) {
                $server = $this->sessions->get($sessionId);
            } else {
                $sessionId = $this->sessions->createSession();
                $server = $this->sessions->get($sessionId);
                $extraHeaders['Mcp-Session-Id'] = $sessionId;
            }

            if ($server === null) {
                return HttpTransportResult::text(500, 'Failed to create session', $extraHeaders);
            }

            $response = $server->handleMessage($message);
            if ($response === null) {
                return HttpTransportResult::empty(202, $extraHeaders);
            }

            return HttpTransportResult::json(200, $response, $extraHeaders);
        }

        if ($sessionId === null || $sessionId === '') {
            return HttpTransportResult::text(400, 'Missing Mcp-Session-Id', $extraHeaders);
        }

        $server = $this->sessions->get($sessionId);
        if ($server === null) {
            return HttpTransportResult::text(404, 'Session not found', $extraHeaders);
        }

        $response = $server->handleMessage($message);

        if ($response === null || (!$hasId && $method !== '')) {
            return HttpTransportResult::empty(202, $extraHeaders);
        }

        return HttpTransportResult::json(200, $response, $extraHeaders);
    }

    /**
     * Обрабатывает JSON-RPC batch
     *
     * @param array<string, string> $headers Заголовки
     * @param list<array<string, mixed>> $batch Сообщения
     *
     * @return HttpTransportResult
     */
    private function handleBatch(array $headers, array $batch): HttpTransportResult
    {
        $responses = [];
        $extraHeaders = $this->corsHeaders();
        $sessionId = $this->getSessionId($headers);

        foreach ($batch as $message) {
            if (!is_array($message)) {
                continue;
            }

            $method = isset($message['method']) && is_string($message['method']) ? $message['method'] : '';

            if ($method === 'initialize') {
                if ($sessionId === null || $this->sessions->get($sessionId) === null) {
                    $sessionId = $this->sessions->createSession();
                    $extraHeaders['Mcp-Session-Id'] = $sessionId;
                }
            } elseif ($sessionId === null || $this->sessions->get($sessionId) === null) {
                return HttpTransportResult::text(400, 'Missing or invalid Mcp-Session-Id', $extraHeaders);
            }

            $server = $sessionId !== null ? $this->sessions->get($sessionId) : null;
            if ($server === null) {
                return HttpTransportResult::text(400, 'Missing or invalid Mcp-Session-Id', $extraHeaders);
            }

            $response = $server->handleMessage($message);
            if ($response !== null) {
                $responses[] = $response;
            }
        }

        if ($responses === []) {
            return HttpTransportResult::empty(202, $extraHeaders);
        }

        return new HttpTransportResult(
            200,
            \quanzo\mcp\helpers\JsonHelper::encode($responses),
            array_merge($extraHeaders, ['Content-Type' => 'application/json'])
        );
    }

    /**
     * Проверяет, что decoded — batch-массив JSON-RPC
     *
     * @param array<mixed> $decoded Декодированное тело
     *
     * @return bool
     */
    private function isJsonRpcBatch(array $decoded): bool
    {
        if ($decoded === []) {
            return false;
        }

        return array_is_list($decoded);
    }

    /**
     * Проверяет Accept на application/json
     *
     * @param string $accept Заголовок Accept
     *
     * @return bool
     */
    private function acceptsJson(string $accept): bool
    {
        return stripos($accept, 'application/json') !== false
            || stripos($accept, '*/*') !== false;
    }

    /**
     * Извлекает Mcp-Session-Id
     *
     * @param array<string, string> $headers Заголовки
     *
     * @return string|null
     */
    private function getSessionId(array $headers): ?string
    {
        return $headers['mcp-session-id'] ?? null;
    }

    /**
     * Проверяет Origin
     *
     * @param array<string, string> $headers Заголовки
     *
     * @return bool
     */
    private function isOriginAllowed(array $headers): bool
    {
        $origin = $headers['origin'] ?? null;
        if ($origin === null || $origin === '') {
            return true;
        }

        if ($this->allowedOrigins !== []) {
            return in_array($origin, $this->allowedOrigins, true);
        }

        $host = parse_url($origin, PHP_URL_HOST);

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * Проверяет Bearer auth
     *
     * @param array<string, string> $headers Заголовки
     *
     * @return bool
     */
    private function isAuthorized(array $headers): bool
    {
        if ($this->bearerToken === null || $this->bearerToken === '') {
            return true;
        }

        $auth = $headers['authorization'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return false;
        }

        return hash_equals($this->bearerToken, trim($m[1]));
    }

    /**
     * Базовые CORS-заголовки
     *
     * @return array<string, string>
     */
    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, GET, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' =>
                'Content-Type, Accept, Authorization, Mcp-Session-Id, MCP-Protocol-Version',
            'Access-Control-Expose-Headers' => 'Mcp-Session-Id',
        ];
    }
}
