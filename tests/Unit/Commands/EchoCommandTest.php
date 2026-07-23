<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use PHPUnit\Framework\TestCase;
use quanzo\mcp\commands\EchoCommand;

/**
 * Класс EchoCommandTest
 *
 * Тестирует работу команды EchoCommand:
 * - корректную обработку обычных строк;
 * - граничные значения (пустая строка, длинная строка);
 * - корректность вычисления длины и формата временной метки.
 */
class EchoCommandTest extends TestCase
{
    /**
     * Тестирует базовый сценарий выполнения команды с обычной строкой
     *
     * @return void
     */
    public function testExecuteWithRegularString(): void
    {
        $command = new EchoCommand();
        $params = ['message' => 'Hello MCP'];

        $result = $command->execute($params);

        self::assertSame('Hello MCP', $result['result']);
        self::assertSame(strlen('Hello MCP'), $result['length']);
        self::assertArrayHasKey('timestamp', $result);
        self::assertNotEmpty($result['timestamp']);
    }

    /**
     * Тестирует выполнение команды с пустой строкой как граничным значением
     *
     * @return void
     */
    public function testExecuteWithEmptyString(): void
    {
        $command = new EchoCommand();
        $params = ['message' => ''];

        $result = $command->execute($params);

        self::assertSame('', $result['result']);
        self::assertSame(0, $result['length']);
    }

    /**
     * Тестирует выполнение команды с очень длинной строкой
     *
     * @return void
     */
    public function testExecuteWithLongString(): void
    {
        $command = new EchoCommand();
        $longMessage = str_repeat('a', 10_000);
        $params = ['message' => $longMessage];

        $result = $command->execute($params);

        self::assertSame($longMessage, $result['result']);
        self::assertSame(10_000, $result['length']);
    }

    /**
     * Тест resilience: отсутствует message → ValidationException
     *
     * @return void
     */
    public function testMissingMessageTriggersValidationError(): void
    {
        $this->expectException(\quanzo\mcp\classes\validation\ValidationException::class);
        (new EchoCommand())->execute([]);
    }

    /**
     * Тест resilience: message не string → ValidationException
     *
     * @return void
     */
    public function testNonStringMessageTriggersValidationError(): void
    {
        $this->expectException(\quanzo\mcp\classes\validation\ValidationException::class);
        (new EchoCommand())->execute(['message' => 123]);
    }
}
