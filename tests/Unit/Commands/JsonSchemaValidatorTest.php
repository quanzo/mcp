<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use quanzo\mcp\classes\validation\JsonSchemaValidator;

/**
 * Класс JsonSchemaValidatorTest
 *
 * Тестирует валидацию по JSON Schema: успех, обязательные поля,
 * типы, enum, pattern, границы и additionalProperties.
 * Валидатор всегда возвращает массив ошибок и не бросает исключения.
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

        $errors = JsonSchemaValidator::validate(
            ['name' => 'John', 'age' => 25],
            $schema
        );

        self::assertSame([], $errors);
    }

    /**
     * Тестирует ошибки при отсутствии обязательных полей
     *
     * @return void
     */
    public function testRequiredFieldsErrors(): void
    {
        $errors = JsonSchemaValidator::validate(
            [],
            ['type' => 'object', 'required' => ['name', 'age']]
        );

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

        $errors = JsonSchemaValidator::validate(
            ['name' => 'A', 'age' => 40],
            $schema
        );

        self::assertNotEmpty($errors);
    }

    /**
     * Data-driven набор ≥10 граничных и заведомо неверных кейсов
     *
     * @return array<string, array{0: array<string, mixed>, 1: array<string, mixed>, 2: bool}>
     */
    public static function resilienceCasesProvider(): array
    {
        $base = [
            'type' => 'object',
            'required' => ['name', 'role', 'score'],
            'additionalProperties' => false,
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'minLength' => 2,
                    'maxLength' => 8,
                    'pattern' => '^[A-Za-z]+$',
                ],
                'role' => [
                    'type' => 'string',
                    'enum' => ['user', 'admin'],
                ],
                'score' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'maximum' => 100,
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'meta' => [
                    'type' => 'object',
                    'properties' => [
                        'note' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        return [
            'valid_edge_min' => [
                ['name' => 'Ab', 'role' => 'user', 'score' => 0],
                $base,
                true,
            ],
            'valid_edge_max' => [
                ['name' => 'Abcdefgh', 'role' => 'admin', 'score' => 100],
                $base,
                true,
            ],
            'wrong_type_name' => [
                ['name' => 123, 'role' => 'user', 'score' => 1],
                $base,
                false,
            ],
            'enum_fail' => [
                ['name' => 'Ab', 'role' => 'root', 'score' => 1],
                $base,
                false,
            ],
            'pattern_fail' => [
                ['name' => 'A1', 'role' => 'user', 'score' => 1],
                $base,
                false,
            ],
            'score_below_min' => [
                ['name' => 'Ab', 'role' => 'user', 'score' => -1],
                $base,
                false,
            ],
            'score_above_max' => [
                ['name' => 'Ab', 'role' => 'user', 'score' => 101],
                $base,
                false,
            ],
            'name_too_short' => [
                ['name' => 'A', 'role' => 'user', 'score' => 1],
                $base,
                false,
            ],
            'additional_property' => [
                ['name' => 'Ab', 'role' => 'user', 'score' => 1, 'extra' => true],
                $base,
                false,
            ],
            'missing_required' => [
                ['name' => 'Ab'],
                $base,
                false,
            ],
            'array_item_wrong_type' => [
                ['name' => 'Ab', 'role' => 'user', 'score' => 1, 'tags' => [1]],
                $base,
                false,
            ],
            'nested_ok' => [
                ['name' => 'Ab', 'role' => 'user', 'score' => 1, 'meta' => ['note' => 'x']],
                $base,
                true,
            ],
        ];
    }

    /**
     * Тест: data-driven валидация не бросает и возвращает ожидаемый успех/ошибку
     *
     * @param array<string, mixed> $data Данные
     * @param array<string, mixed> $schema Схема
     * @param bool $expectValid Ожидается ли успех
     *
     * @return void
     */
    #[DataProvider('resilienceCasesProvider')]
    public function testResilienceDataSet(array $data, array $schema, bool $expectValid): void
    {
        $errors = JsonSchemaValidator::validate($data, $schema);
        self::assertIsArray($errors);
        if ($expectValid) {
            self::assertSame([], $errors);
        } else {
            self::assertNotEmpty($errors);
        }
    }
}
