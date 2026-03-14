<?php

/**
 * Класс ValidationException
 *
 * Исключение, выбрасываемое при ошибке валидации входных параметров команды.
 * Содержит детальную информацию об ошибках валидации.
 */

namespace app\modules\neuron\mcp\commands;

class ValidationException extends \RuntimeException
{
    /**
     * Массив ошибок валидации
     * @var array
     */
    private array $validationErrors;

    /**
     * Конструктор ValidationException
     *
     * @param array $validationErrors Массив ошибок валидации
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
     * @return array Массив ошибок валидации
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}
