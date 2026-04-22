<?php

namespace quanzo\mcp\classes\validation;

/**
 * Класс ValidationException
 *
 * Исключение, выбрасываемое при ошибке валидации входных параметров команды.
 * Содержит детальную информацию об ошибках валидации.
 */
class ValidationException extends \RuntimeException
{
    /**
     * Массив ошибок валидации
     *
     * @var array<int, array{property?: string, message?: string}>
     */
    private array $validationErrors;

    /**
     * Конструктор ValidationException
     *
     * @param array<int, array{property?: string, message?: string}> $validationErrors Массив ошибок валидации
     * @param string $message Сообщение об ошибке
     */
    public function __construct(array $validationErrors, string $message = "Validation failed")
    {
        $this->validationErrors = $validationErrors;
        parent::__construct($message . ': ' . json_encode($validationErrors));
    }

    /**
     * Возвращает массив ошибок валидации
     *
     * @return array<int, array{property?: string, message?: string}>
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}
