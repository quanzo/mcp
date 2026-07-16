<?php

namespace quanzo\mcp\classes\validation;

/**
 * Класс JsonSchemaValidator
 *
 * Реализует валидацию данных по JSON Schema (подмножество).
 * Поддерживает type, required, enum, min/max, minLength/maxLength, pattern,
 * properties, items, additionalProperties: false.
 * Не бросает исключения — возвращает список ошибок (пустой = OK).
 *
 * Пример использования:
 *   $errors = JsonSchemaValidator::validate($data, $schema);
 *   if ($errors !== []) { throw new ValidationException($errors); }
 */
class JsonSchemaValidator
{
    /**
     * Валидирует данные по JSON Schema
     *
     * @param array $data Данные для валидации
     * @param array $schema Схема валидации в формате JSON Schema
     *
     * @return array Массив ошибок валидации. Пустой массив означает успешную валидации.
     */
    public static function validate(array $data, array $schema): array
    {
        $errors = [];

        // Проверка типа
        if (isset($schema['type'])) {
            $typeErrors = self::validateType($data, $schema);
            if (!empty($typeErrors)) {
                $errors = array_merge($errors, $typeErrors);
            }
        }

        // Проверка обязательных полей
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $requiredField) {
                if (!array_key_exists($requiredField, $data)) {
                    $errors[] = [
                        'property' => $requiredField,
                        'message' => "Field '$requiredField' is required"
                    ];
                }
            }
        }

        // Проверка свойств объекта
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $propertyName => $propertySchema) {
                if (array_key_exists($propertyName, $data)) {
                    $propertyErrors = self::validateProperty($data[$propertyName], $propertySchema, $propertyName);
                    $errors = array_merge($errors, $propertyErrors);
                }
            }
        }

        // Запрет дополнительных свойств
        if (isset($schema['additionalProperties']) && $schema['additionalProperties'] === false && is_array($data)) {
            $allowedKeys = isset($schema['properties']) && is_array($schema['properties'])
                ? array_keys($schema['properties'])
                : [];
            foreach (array_keys($data) as $key) {
                if (!in_array($key, $allowedKeys, true)) {
                    $errors[] = [
                        'property' => (string) $key,
                        'message' => "Additional property '$key' is not allowed"
                    ];
                }
            }
        }

        return $errors;
    }

    /**
     * Валидирует тип данных
     *
     * @param mixed $value Значение для проверки
     * @param array $schema Схема валидации с указанием типа
     *
     * @return array Массив ошибок валидации типа
     */
    private static function validateType($value, array $schema): array
    {
        $errors = [];
        $type = $schema['type'];

        switch ($type) {
            case 'string':
                if (!is_string($value)) {
                    $errors[] = [
                        'property' => '',
                        'message' => "Expected string, got " . gettype($value)
                    ];
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    $errors[] = [
                        'property' => '',
                        'message' => "Expected number, got " . gettype($value)
                    ];
                }
                break;

            case 'integer':
                if (!is_int($value)) {
                    $errors[] = [
                        'property' => '',
                        'message' => "Expected integer, got " . gettype($value)
                    ];
                }
                break;

            case 'boolean':
                if (!is_bool($value)) {
                    $errors[] = [
                        'property' => '',
                        'message' => "Expected boolean, got " . gettype($value)
                    ];
                }
                break;

            case 'array':
                if (!is_array($value)) {
                    $errors[] = [
                        'property' => '',
                        'message' => "Expected array, got " . gettype($value)
                    ];
                }
                break;

            case 'object':
                if (!is_array($value)) {
                    $errors[] = [
                        'property' => '',
                        'message' => "Expected object, got " . gettype($value)
                    ];
                }
                break;
        }

        return $errors;
    }

    /**
     * Валидирует свойство объекта по схеме
     *
     * @param mixed $value Значение свойства
     * @param array $schema Схема валидации свойства
     * @param string $propertyPath Путь к свойству (для вложенных свойств)
     *
     * @return array Массив ошибок валидации свойства
     */
    private static function validateProperty($value, array $schema, string $propertyPath): array
    {
        $errors = [];

        if (isset($schema['type'])) {
            $propertyErrors = self::validateType($value, $schema);
            foreach ($propertyErrors as $error) {
                $error['property'] = $propertyPath . ($error['property'] ? '.' . $error['property'] : '');
                $errors[] = $error;
            }
        }

        // Проверка enum
        if (isset($schema['enum']) && is_array($schema['enum'])) {
            if (!in_array($value, $schema['enum'], true)) {
                $errors[] = [
                    'property' => $propertyPath,
                    'message' => "Value must be one of: " . implode(', ', $schema['enum'])
                ];
            }
        }

        // Проверка минимального значения
        if (isset($schema['minimum']) && is_numeric($value)) {
            if ($value < $schema['minimum']) {
                $errors[] = [
                    'property' => $propertyPath,
                    'message' => "Value must be at least {$schema['minimum']}"
                ];
            }
        }

        // Проверка максимального значения
        if (isset($schema['maximum']) && is_numeric($value)) {
            if ($value > $schema['maximum']) {
                $errors[] = [
                    'property' => $propertyPath,
                    'message' => "Value must be at most {$schema['maximum']}"
                ];
            }
        }

        // Проверка минимальной длины строки
        if (isset($schema['minLength']) && is_string($value)) {
            if (mb_strlen($value) < $schema['minLength']) {
                $errors[] = [
                    'property' => $propertyPath,
                    'message' => "String must be at least {$schema['minLength']} characters long"
                ];
            }
        }

        // Проверка максимальной длины строки
        if (isset($schema['maxLength']) && is_string($value)) {
            if (mb_strlen($value) > $schema['maxLength']) {
                $errors[] = [
                    'property' => $propertyPath,
                    'message' => "String must be at most {$schema['maxLength']} characters long"
                ];
            }
        }

        // Проверка по регулярному выражению (JSON Schema pattern)
        if (isset($schema['pattern']) && is_string($schema['pattern']) && is_string($value)) {
            $regex = '#^(?:' . $schema['pattern'] . ')$#u';
            if (@preg_match($regex, $value) !== 1) {
                $errors[] = [
                    'property' => $propertyPath,
                    'message' => "String does not match pattern"
                ];
            }
        }

        // Рекурсивная проверка вложенных объектов
        if (isset($schema['properties']) && is_array($value)) {
            foreach ($schema['properties'] as $nestedProperty => $nestedSchema) {
                if (array_key_exists($nestedProperty, $value)) {
                    $nestedErrors = self::validateProperty(
                        $value[$nestedProperty],
                        $nestedSchema,
                        $propertyPath . '.' . $nestedProperty
                    );
                    $errors = array_merge($errors, $nestedErrors);
                }
            }
        }

        // Рекурсивная проверка элементов массива
        if (isset($schema['items']) && is_array($value)) {
            foreach ($value as $index => $item) {
                $itemErrors = self::validateProperty($item, $schema['items'], $propertyPath . "[$index]");
                $errors = array_merge($errors, $itemErrors);
            }
        }

        return $errors;
    }
}
