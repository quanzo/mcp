<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use PHPUnit\Framework\TestCase;
use quanzo\mcp\commands\UserCommand;
use quanzo\mcp\validation\ValidationException;

/**
 * Класс UserCommandTest
 *
 * Тестирует создание пользователя в UserCommand,
 * в том числе:
 * - корректные данные;
 * - граничные значения имени, возраста, ролей;
 * - некорректные email и границы возраста;
 * - кейс с уже занятым email.
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
     * Тестирует кейс с уже занятым email
     *
     * @return void
     */
    public function testDuplicateEmailTriggersValidationException(): void
    {
        $command = new UserCommand();

        try {
            $command->execute([
                'name' => 'Admin',
                'email' => 'admin@example.com',
            ]);
            self::fail('Ожидалось исключение ValidationException для занятого email');
        } catch (ValidationException $exception) {
            $errors = $exception->getValidationErrors();
            self::assertNotEmpty($errors);
            self::assertSame('email', $errors[0]['property']);
        }
    }
}
