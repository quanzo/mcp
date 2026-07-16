<?php

declare(strict_types=1);

namespace Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use quanzo\mcp\classes\McpServer;
use quanzo\mcp\classes\dto\mcp\ServerInfo;
use quanzo\mcp\classes\transport\StdioTransport;
use quanzo\mcp\helpers\JsonHelper;

/**
 * Класс StdioTransportTest
 *
 * Проверяет framing stdio: parse errors, пустые строки, notifications,
 * продолжение работы после мусорного ввода.
 */
class StdioTransportTest extends TestCase
{
    /**
     * Запускает транспорт на заданном вводе и возвращает stdout
     *
     * @param string $input Содержимое STDIN
     *
     * @return string Ответ STDOUT
     */
    private function runTransport(string $input): string
    {
        $inPath = tempnam(sys_get_temp_dir(), 'mcp_in_');
        $outPath = tempnam(sys_get_temp_dir(), 'mcp_out_');
        self::assertNotFalse($inPath);
        self::assertNotFalse($outPath);

        file_put_contents($inPath, $input);

        $inputHandle = fopen($inPath, 'rb');
        $outputHandle = fopen($outPath, 'wb');
        self::assertIsResource($inputHandle);
        self::assertIsResource($outputHandle);

        $server = new McpServer(new ServerInfo('stdio-test', '1.0.0'), new NullLogger());
        $transport = new StdioTransport($server, new NullLogger(), $inputHandle, $outputHandle);
        $transport->run();

        fclose($inputHandle);
        fclose($outputHandle);

        $output = file_get_contents($outPath);
        @unlink($inPath);
        @unlink($outPath);

        return $output === false ? '' : $output;
    }

    /**
     * Тест: невалидный JSON → -32700, процесс не падает
     *
     * @return void
     */
    public function testInvalidJsonReturnsParseError(): void
    {
        $output = $this->runTransport("{not-json\n");
        $line = trim($output);
        $decoded = JsonHelper::decode($line, true);

        self::assertSame(-32700, $decoded['error']['code']);
    }

    /**
     * Тест: пустые строки пропускаются без ответа
     *
     * @return void
     */
    public function testEmptyLinesProduceNoOutput(): void
    {
        $output = $this->runTransport("\n\n   \n");
        self::assertSame('', trim($output));
    }

    /**
     * Тест: notification без id → нет ответа в STDOUT
     *
     * @return void
     */
    public function testNotificationProducesNoStdout(): void
    {
        $init = JsonHelper::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 't', 'version' => '1'],
            ],
        ]);
        $notification = JsonHelper::encode([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]);

        $output = $this->runTransport($init . "\n" . $notification . "\n");
        $lines = array_values(array_filter(explode("\n", trim($output))));

        // Только ответ на initialize
        self::assertCount(1, $lines);
        $decoded = JsonHelper::decode($lines[0], true);
        self::assertArrayHasKey('result', $decoded);
    }

    /**
     * Тест: валидный initialize даёт JSON-RPC result
     *
     * @return void
     */
    public function testValidInitializeWritesResponse(): void
    {
        $input = JsonHelper::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 't', 'version' => '1'],
            ],
        ]) . "\n";

        $output = $this->runTransport($input);
        $decoded = JsonHelper::decode(trim($output), true);

        self::assertSame('2025-03-26', $decoded['result']['protocolVersion']);
    }

    /**
     * Тест resilience: мусор → затем валидный ping (цикл продолжается)
     *
     * @return void
     */
    public function testContinuesAfterGarbageThenPing(): void
    {
        $garbage = "{bad\n";
        $init = JsonHelper::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 't', 'version' => '1'],
            ],
        ]) . "\n";
        $ping = JsonHelper::encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'ping',
        ]) . "\n";

        $output = $this->runTransport($garbage . $init . $ping);
        $lines = array_values(array_filter(explode("\n", trim($output))));

        self::assertCount(3, $lines);

        $parseError = JsonHelper::decode($lines[0], true);
        self::assertSame(-32700, $parseError['error']['code']);

        $initResult = JsonHelper::decode($lines[1], true);
        self::assertArrayHasKey('result', $initResult);

        $pingResult = JsonHelper::decode($lines[2], true);
        self::assertSame(2, $pingResult['id']);
        self::assertArrayHasKey('result', $pingResult);
    }

    /**
     * Тест: JSON не-object (массив-не-batch на верхнем уровне как строка числа) → parse/format error
     *
     * @return void
     */
    public function testNonObjectJsonMessageReturnsParseError(): void
    {
        $output = $this->runTransport("42\n");
        $decoded = JsonHelper::decode(trim($output), true);
        self::assertSame(-32700, $decoded['error']['code']);
    }
}
