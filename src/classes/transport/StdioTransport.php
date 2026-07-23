<?php

declare(strict_types=1);

namespace quanzo\mcp\classes\transport;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use quanzo\mcp\classes\McpServer;
use quanzo\mcp\classes\dto\JsonRpcResponse;
use quanzo\mcp\helpers\JsonHelper;

/**
 * Класс StdioTransport
 *
 * Стандартный MCP-транспорт stdio: newline-delimited JSON-RPC через STDIN/STDOUT.
 * Логи пишутся только через логгер (файл/STDERR), не в STDOUT.
 *
 * Пример использования:
 *   $transport = new StdioTransport($mcpServer, $logger);
 *   $transport->run();
 */
class StdioTransport
{
    /**
     * Протокольное ядро MCP
     *
     * @var McpServer
     */
    private McpServer $server;

    /**
     * Логгер
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Поток ввода
     *
     * @var resource
     */
    private $input;

    /**
     * Поток вывода
     *
     * @var resource
     */
    private $output;

    /**
     * Конструктор StdioTransport
     *
     * @param McpServer $server Протокольное ядро
     * @param LoggerInterface|null $logger Логгер
     * @param resource|null $input Поток ввода (по умолчанию STDIN)
     * @param resource|null $output Поток вывода (по умолчанию STDOUT)
     */
    public function __construct(
        McpServer $server,
        ?LoggerInterface $logger = null,
        $input = null,
        $output = null
    ) {
        $this->server = $server;
        $this->logger = $logger ?? new NullLogger();
        $this->input = $input ?? STDIN;
        $this->output = $output ?? STDOUT;
    }

    /**
     * Запускает цикл чтения сообщений до EOF
     *
     * Каждая строка — отдельное JSON-RPC сообщение.
     * Битый JSON → ответ -32700, цикл продолжается (сервер не падает).
     * Notification (без id) → в STDOUT ничего не пишется.
     *
     * @return void
     */
    public function run(): void
    {
        $this->logger->info('Stdio MCP transport started');

        while (!feof($this->input)) {
            $line = fgets($this->input);

            if ($line === false || $line === '') {
                continue;
            }

            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $this->logger->debug('Request received', ['raw' => $trimmed]);

            try {
                /** @var array<string, mixed> $message */
                $message = JsonHelper::decode($trimmed, true);
                if (!is_array($message)) {
                    $this->writeResponse(JsonRpcResponse::parseError('Message must be a JSON object'));
                    continue;
                }

                $response = $this->server->handleMessage($message);
                if ($response !== null) {
                    $this->writeResponse($response);
                }
            } catch (\JsonException $e) {
                $this->writeResponse(JsonRpcResponse::parseError($e->getMessage()));
            } catch (\Throwable $e) {
                $this->logger->error('Unhandled transport error', ['message' => $e->getMessage()]);
                $this->writeResponse(
                    JsonRpcResponse::error(null, -32603, 'Internal error', $e->getMessage())->toArray()
                );
            }
        }

        $this->logger->info('Stdio MCP transport stopped');
    }

    /**
     * Пишет JSON-RPC ответ в STDOUT одной строкой
     *
     * @param array<string, mixed> $response Ответ
     *
     * @return void
     */
    private function writeResponse(array $response): void
    {
        fwrite($this->output, JsonHelper::encode($response) . "\n");
        $this->logger->debug('Response sent');
    }
}
