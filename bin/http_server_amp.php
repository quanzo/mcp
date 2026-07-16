<?php

/**
 * Streamable HTTP MCP server (Amp)
 *
 * Тот же контракт /mcp, что и bin/http_server.php.
 *
 * Пример:
 *   php bin/http_server_amp.php 127.0.0.1 8080
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/vendor/autoload.php';

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use quanzo\mcp\classes\McpServerFactory;
use quanzo\mcp\classes\log\FileLogger;
use quanzo\mcp\classes\transport\StreamableHttpTransport;
use Revolt\EventLoop;

if (php_sapi_name() !== 'cli') {
    echo "Запускайте из CLI: php http_server_amp.php [host] [port]\n";
    exit(1);
}

$host = $argv[1] ?? '127.0.0.1';
$port = isset($argv[2]) && is_numeric($argv[2]) ? (int) $argv[2] : 8080;

if ($port < 1 || $port > 65535) {
    fwrite(STDERR, "Ошибка: Порт должен быть в диапазоне 1-65535\n");
    exit(1);
}

foreach (['logs', 'config', 'data'] as $dir) {
    $path = $projectRoot . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

$fileLogger = new FileLogger($projectRoot . '/logs/mcp-http-amp.log', \Psr\Log\LogLevel::INFO);
$bearer = getenv('MCP_HTTP_BEARER') ?: null;
if ($bearer === '') {
    $bearer = null;
}
$originsEnv = getenv('MCP_ALLOWED_ORIGINS') ?: '';
$origins = $originsEnv !== '' ? array_values(array_filter(array_map('trim', explode(',', $originsEnv)))) : [];

$mcpServer = McpServerFactory::createDefault($projectRoot, $fileLogger);
$transport = new StreamableHttpTransport($mcpServer, $bearer, $origins);

$log = new Logger('mcp-http-amp');
$log->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$server = SocketHttpServer::createForDirectAccess(
    $log,
    true,
    1000,
    10,
    1000,
    ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD']
);
$server->expose("{$host}:{$port}");

$errorHandler = new DefaultErrorHandler();

$requestHandler = new ClosureRequestHandler(static function (Request $request) use ($transport): Response {
    $headers = [];
    foreach ($request->getHeaders() as $name => $values) {
        $headers[strtolower($name)] = $values[0] ?? '';
    }

    $body = $request->getBody()->buffer();
    $result = $transport->handleHttpRequest(
        $request->getMethod(),
        $request->getUri()->getPath(),
        $headers,
        $body
    );

    return new Response(
        $result->getStatusCode(),
        $result->getHeaders(),
        $result->getBody()
    );
});

$server->start($requestHandler, $errorHandler);

echo "MCP Streamable HTTP (Amp) on http://{$host}:{$port}/mcp\n";

EventLoop::run();
