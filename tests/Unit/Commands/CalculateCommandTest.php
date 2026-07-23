<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use PHPUnit\Framework\TestCase;
use quanzo\mcp\commands\CalculateCommand;

/**
 * Класс CalculateCommandTest
 *
 * Тестирует выполнение математических операций в CalculateCommand,
 * включая граничные значения и некорректные данные.
 */
class CalculateCommandTest extends TestCase
{
    /**
     * Тестирует успешные математические операции
     *
     * @return void
     */
    public function testSuccessfulOperations(): void
    {
        $command = new CalculateCommand();

        $cases = [
            ['operation' => 'add', 'a' => 2, 'b' => 3, 'expected' => 5],
            ['operation' => 'subtract', 'a' => 2, 'b' => 5, 'expected' => -3],
            ['operation' => 'multiply', 'a' => 0, 'b' => 10, 'expected' => 0],
            ['operation' => 'divide', 'a' => 10, 'b' => 2, 'expected' => 5],
            ['operation' => 'add', 'a' => 1.5, 'b' => 2.3, 'expected' => 3.8],
        ];

        foreach ($cases as $case) {
            $result = $command->execute([
                'operation' => $case['operation'],
                'a' => $case['a'],
                'b' => $case['b'],
            ]);

            self::assertSame($case['operation'], $result['operation']);
            self::assertEquals($case['expected'], $result['result']);
            self::assertArrayHasKey('expression', $result);
            self::assertArrayHasKey('timestamp', $result);
        }
    }

    /**
     * Тестирует граничное условие деления на ноль
     *
     * @return void
     */
    public function testDivideByZeroThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $command = new CalculateCommand();
        $command->execute([
            'operation' => 'divide',
            'a' => 10,
            'b' => 0,
        ]);
    }

    /**
     * Тестирует некорректную операцию
     *
     * @return void
     */
    public function testUnknownOperationThrowsException(): void
    {
        $this->expectException(\quanzo\mcp\classes\validation\ValidationException::class);

        $command = new CalculateCommand();
        $command->execute([
            'operation' => 'pow',
            'a' => 2,
            'b' => 3,
        ]);
    }

    /**
     * Тест resilience: a/b не числа (массивы) → ValidationException
     *
     * @return void
     */
    public function testNonNumericOperandsTriggerValidationError(): void
    {
        $this->expectException(\quanzo\mcp\classes\validation\ValidationException::class);

        (new CalculateCommand())->execute([
            'operation' => 'add',
            'a' => ['x'],
            'b' => ['y'],
        ]);
    }

    /**
     * Тест resilience: отсутствуют обязательные поля → ValidationException
     *
     * @return void
     */
    public function testMissingFieldsTriggerValidationError(): void
    {
        $this->expectException(\quanzo\mcp\classes\validation\ValidationException::class);
        (new CalculateCommand())->execute(['operation' => 'add']);
    }
}
