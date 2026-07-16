<?php

/**
 * Демо-клиент стандартного MCP (stdio)
 */

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/vendor/autoload.php';

use quanzo\mcp\classes\client\MCPClient;
use quanzo\mcp\helpers\JsonHelper;

$client = new MCPClient($projectRoot . '/bin/mcp_server.php');

try {
    echo "=== tools/list ===\n";
    echo JsonHelper::encode($client->listTools(), JsonHelper::DEFAULT_PRETTY_FLAGS) . "\n\n";

    echo "=== tools/call echo ===\n";
    echo JsonHelper::encode($client->callTool('echo', ['message' => 'Hello MCP']), JsonHelper::DEFAULT_PRETTY_FLAGS) . "\n\n";

    echo "=== tools/call calculate ===\n";
    echo JsonHelper::encode(
        $client->callTool('calculate', ['operation' => 'add', 'a' => 2, 'b' => 3]),
        JsonHelper::DEFAULT_PRETTY_FLAGS
    ) . "\n\n";

    echo "=== resources/list ===\n";
    echo JsonHelper::encode($client->listResources(), JsonHelper::DEFAULT_PRETTY_FLAGS) . "\n\n";

    echo "=== ping ===\n";
    echo JsonHelper::encode($client->ping(), JsonHelper::DEFAULT_PRETTY_FLAGS) . "\n";
} catch (\Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    $client->close();
}
