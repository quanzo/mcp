<?php

/**
 * Streamable HTTP MCP server
 *
 * CLI: запускает php -S на 127.0.0.1:8080 с этим же файлом как router.
 * Router (cli-server): обрабатывает POST/GET/DELETE /mcp.
 *
 * Пример:
 *   php bin/http_server.php -h 127.0.0.1 -p 8080
 *   php bin/http_server.php -b my-bearer-token
 *
 * Endpoint агента: http://127.0.0.1:8080/mcp
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/vendor/autoload.php';

use quanzo\mcp\classes\McpServerFactory;
use quanzo\mcp\classes\log\FileLogger;
use quanzo\mcp\classes\transport\StreamableHttpTransport;
use quanzo\mcp\helpers\JsonHelper;

/**
 * Возвращает singleton-транспорт в процессе php -S
 *
 * @param string $projectRoot Корень проекта
 *
 * @return StreamableHttpTransport
 */
function mcp_http_transport(string $projectRoot): StreamableHttpTransport
{
    static $transport = null;

    if ($transport instanceof StreamableHttpTransport) {
        return $transport;
    }

    foreach (['logs', 'config', 'data'] as $dir) {
        $path = $projectRoot . '/' . $dir;
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    $logger = new FileLogger(
        $projectRoot . '/logs/mcp-http.log',
        \Psr\Log\LogLevel::INFO
    );

    $bearer = getenv('MCP_HTTP_BEARER') ?: null;
    if ($bearer === '') {
        $bearer = null;
    }

    $originsEnv = getenv('MCP_ALLOWED_ORIGINS') ?: '';
    $origins = $originsEnv !== '' ? array_values(array_filter(array_map('trim', explode(',', $originsEnv)))) : [];

    $server = McpServerFactory::createDefault($projectRoot, $logger);
    $transport = new StreamableHttpTransport($server, $bearer, $origins);

    return $transport;
}

/**
 * Нормализует заголовки запроса в lowercase keys
 *
 * @return array<string, string>
 */
function mcp_request_headers(): array
{
    $headers = [];

    if (function_exists('getallheaders')) {
        $raw = getallheaders();
        if (is_array($raw)) {
            foreach ($raw as $name => $value) {
                $headers[strtolower((string) $name)] = (string) $value;
            }
        }
    }

    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_') && is_string($value)) {
            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$name] = $value;
        }
    }

    if (isset($_SERVER['CONTENT_TYPE']) && is_string($_SERVER['CONTENT_TYPE'])) {
        $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
    }

    return $headers;
}

if (php_sapi_name() === 'cli-server') {
    $transport = mcp_http_transport($projectRoot);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $body = file_get_contents('php://input') ?: '';
    $result = $transport->handleHttpRequest($method, $uri, mcp_request_headers(), $body);
    $result->emit();

    return true;
}

if (php_sapi_name() !== 'cli') {
    http_response_code(500);
    header('Content-Type: application/json');
    echo JsonHelper::encode(['error' => 'Use CLI or php built-in server']);
    exit(1);
}

$host = '127.0.0.1';
$port = 8080;
$options = getopt('p:h:b:', ['port:', 'host:', 'bearer:', 'help']);

if (isset($options['help'])) {
    echo "Использование: php http_server.php [опции]\n\n";
    echo "Опции:\n";
    echo "  -p, --port=PORT       Порт (по умолчанию: 8080)\n";
    echo "  -h, --host=HOST       Хост (по умолчанию: 127.0.0.1)\n";
    echo "  -b, --bearer=TOKEN    Опциональный Bearer token\n";
    echo "      --help            Справка\n\n";
    echo "Endpoint: http://HOST:PORT/mcp\n";
    echo "Env: MCP_HTTP_BEARER, MCP_ALLOWED_ORIGINS\n";
    exit(0);
}

if (isset($options['p'])) {
    $port = (int) $options['p'];
} elseif (isset($options['port'])) {
    $port = (int) $options['port'];
}

if (isset($options['h'])) {
    $host = $options['h'];
} elseif (isset($options['host'])) {
    $host = $options['host'];
}

if (isset($options['b'])) {
    putenv('MCP_HTTP_BEARER=' . $options['b']);
} elseif (isset($options['bearer'])) {
    putenv('MCP_HTTP_BEARER=' . $options['bearer']);
}

if ($port < 1 || $port > 65535) {
    fwrite(STDERR, "Ошибка: Порт должен быть в диапазоне 1-65535\n");
    exit(1);
}

echo "MCP Streamable HTTP on http://{$host}:{$port}/mcp\n";

$cmd = sprintf('php -S %s:%d %s', $host, $port, escapeshellarg(__FILE__));
passthru($cmd, $exitCode);
exit($exitCode);
