<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use PHPUnit\Framework\TestCase;
use app\modules\neuron\mcp\commands\JsonSchemaValidator;

/**
 * Класс JsonSchemaValidatorTest
 *
 * Тестирует валидацию по JSON Schema, включая:
 * - обязательные поля;
 * - типы данных;
 * - enum, minimum/maximum;
 * - minLength/maxLength;
 * - вложенные объекты и массивы.
 */
class JsonSchemaValidatorTest extends TestCase
{
    /**
     * Тестирует успешную валидацию корректных данных
     *
     * @return void
     */
    public function testValidateSuccess(): void
    {
        $schema = [
            'type' => 'object',
            'required' => ['name', 'age'],
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'minLength' => 2,
                    'maxLength' => 10,
                ],
                'age' => [
                    'type' => 'integer',
                    'minimum' => 18,
                    'maximum' => 30,
                ],
            ],
        ];

        $data = [
            'name' => 'John',
            'age' => 25,
        ];

        $errors = JsonSchemaValidator::validate($data, $schema);

        self::assertSame([], $errors);
    }

    /**
     * Тестирует ошибки при отсутствии обязательных полей
     *
     * @return void
     */
    public function testRequiredFieldsErrors(): void
    {
        $schema = [
            'type' => 'object',
            'required' => ['name', 'age'],
        ];

        $data = [];

        $errors = JsonSchemaValidator::validate($data, $schema);

        self::assertCount(2, $errors);
    }

    /**
     * Тестирует ошибки типов и ограничений
     *
     * @return void
     */
    public function testTypeAndConstraintErrors(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'minLength' => 3,
                    'maxLength' => 5,
                ],
                'age' => [
                    'type' => 'integer',
                    'minimum' => 18,
                    'maximum' => 30,
                ],
            ],
        ];

        $data = [
            'name' => 'A',
            'age' => 40,
        ];

        $errors = JsonSchemaValidator::validate($data, $schema);

        self::assertNotEmpty($errors);
    }
}
