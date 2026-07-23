<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use quanzo\mcp\classes\McpServer;
use quanzo\mcp\classes\dto\mcp\ServerInfo;
use quanzo\mcp\classes\validation\ValidationException;
use quanzo\mcp\commands\UserCommand;

/**
 * Класс UserCommandTest
 *
 * Тестирует создание пользователя в UserCommand:
 * корректные данные, границы, schema errors, business isError (занятый email).
 */
class UserCommandTest extends TestCase
{
    /**
     * Тестирует успешное создание пользователя с минимальным набором данных
     *
     * @return void
     */
    public function testCreateUserWithRequiredFieldsOnly(): void
    {
        $command = new UserCommand();

        $result = $command->execute([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        self::assertArrayHasKey('id', $result);
        self::assertSame('John Doe', $result['name']);
        self::assertSame('john@example.com', $result['email']);
        self::assertNull($result['age']);
        self::assertSame(['user'], $result['roles']);
        self::assertSame('active', $result['status']);
    }

    /**
     * Тестирует успешное создание пользователя с граничными значениями
     *
     * @return void
     */
    public function testCreateUserWithBoundaryValues(): void
    {
        $command = new UserCommand();

        $result = $command->execute([
            'name' => str_repeat('a', 50),
            'email' => 'valid@example.com',
            'age' => 18,
            'roles' => ['user', 'admin'],
        ]);

        self::assertSame(18, $result['age']);
        self::assertSame(['user', 'admin'], $result['roles']);
    }

    /**
     * Тестирует некорректный формат email и длину имени
     *
     * @return void
     */
    public function testInvalidEmailAndNameTriggersValidationError(): void
    {
        $this->expectException(ValidationException::class);

        $command = new UserCommand();
        $command->execute([
            'name' => 'A',
            'email' => 'not-an-email',
        ]);
    }

    /**
     * Тестирует граничные значения возраста вне допустимого диапазона
     *
     * @return void
     */
    public function testAgeOutOfRangeTriggersValidationError(): void
    {
        $this->expectException(ValidationException::class);

        $command = new UserCommand();
        $command->execute([
            'name' => 'John Doe',
            'email' => 'john2@example.com',
            'age' => 10,
        ]);
    }

    /**
     * Тест: пустой roles нарушает minItems → ValidationException
     *
     * @return void
     */
    public function testEmptyRolesTriggersMinItemsValidation(): void
    {
        $this->expectException(ValidationException::class);

        $command = new UserCommand();
        $command->execute([
            'name' => 'John Doe',
            'email' => 'john3@example.com',
            'roles' => [],
        ]);
    }

    /**
     * Тест: занятый email → RuntimeException (business, не schema)
     *
     * @return void
     */
    public function testDuplicateEmailThrowsRuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Email уже используется');

        $command = new UserCommand();
        $command->execute([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);
    }

    /**
     * Тест: через McpServer занятый email → result.isError, не -32602
     *
     * @return void
     */
    public function testDuplicateEmailBecomesIsErrorViaMcpServer(): void
    {
        $server = new McpServer(new ServerInfo('t', '1.0.0'), new NullLogger());
        $server->registerCommand(new UserCommand());
        $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => [],
                'clientInfo' => ['name' => 't', 'version' => '1'],
            ],
        ]);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'user_create',
                'arguments' => [
                    'name' => 'Admin',
                    'email' => 'admin@example.com',
                ],
            ],
        ]);

        self::assertArrayHasKey('result', $response);
        self::assertTrue($response['result']['isError']);
        self::assertStringContainsString('Email уже используется', $response['result']['content'][0]['text']);
    }

    /**
     * Тест: age=120 граница OK
     *
     * @return void
     */
    public function testAgeMaxBoundaryOk(): void
    {
        $command = new UserCommand();
        $result = $command->execute([
            'name' => 'Old',
            'email' => 'old@example.com',
            'age' => 120,
        ]);
        self::assertSame(120, $result['age']);
    }

    /**
     * Тест: age=121 → ValidationException
     *
     * @return void
     */
    public function testAgeAboveMaxFails(): void
    {
        $this->expectException(ValidationException::class);
        (new UserCommand())->execute([
            'name' => 'Old',
            'email' => 'old2@example.com',
            'age' => 121,
        ]);
    }

    /**
     * Тест: name длиной 51 → ValidationException
     *
     * @return void
     */
    public function testNameTooLongFails(): void
    {
        $this->expectException(ValidationException::class);
        (new UserCommand())->execute([
            'name' => str_repeat('a', 51),
            'email' => 'long@example.com',
        ]);
    }

    /**
     * Тест: неверная роль → ValidationException
     *
     * @return void
     */
    public function testInvalidRoleFails(): void
    {
        $this->expectException(ValidationException::class);
        (new UserCommand())->execute([
            'name' => 'John',
            'email' => 'john4@example.com',
            'roles' => ['superadmin'],
        ]);
    }

    /**
     * Тест: лишнее свойство → ValidationException
     *
     * @return void
     */
    public function testAdditionalPropertyFails(): void
    {
        $this->expectException(ValidationException::class);
        (new UserCommand())->execute([
            'name' => 'John',
            'email' => 'john5@example.com',
            'extra' => true,
        ]);
    }
}
