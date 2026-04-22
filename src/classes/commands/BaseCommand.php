<?php

namespace quanzo\mcp\commands;

use quanzo\mcp\interfaces\CommandInterface;
use quanzo\mcp\validation\JsonSchemaValidator;
use quanzo\mcp\validation\ValidationException;

/**
 * Абстрактный класс BaseCommand
 *
 * Предоставляет базовую реализацию интерфейса CommandInterface.
 * Упрощает создание новых команд, предоставляя стандартную реализацию
 * валидации и выполнения.
 */
abstract class BaseCommand implements CommandInterface
{
    /**
     * Имя команды
     * @var string
     */
    protected string $name;

    /**
     * Описание команды
     * @var string
     */
    protected string $description;

    /**
     * Возвращает имя команды
     *
     * @return string Имя команды
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Возвращает описание команды
     *
     * @return string Описание команды
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Возвращает схему входных параметров по умолчанию
     *
     * @return array Схема входных параметров в формате JSON Schema
     */
    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [],
            'required'   => []
        ];
    }

    /**
     * Валидирует входные параметры по схеме команды
     *
     * @param array $params Входные параметры для валидации
     *
     * @return void
     *
     * @throws ValidationException Если параметры не соответствуют схеме
     */
    public function validateInput(array $params): void
    {
        $schema = $this->getInputSchema();
        $errors = JsonSchemaValidator::validate($params, $schema);

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Выполняет команду с предварительной валидацией входных параметров
     *
     * @param array $params Входные параметры команды
     *
     * @return array Результат выполнения команды
     *
     * @throws ValidationException Если параметры не прошли валидацию
     * @throws \Exception Если произошла ошибка при выполнении
     */
    public function execute(array $params): array
    {
        $this->validateInput($params);
        return $this->doExecute($params);
    }

    /**
     * Абстрактный метод для реализации конкретной логики команды
     *
     * @param array $params Входные параметры команды (уже прошедшие валидацию)
     *
     * @return array Результат выполнения команды
     *
     * @throws \Exception Если произошла ошибка при выполнении
     */
    abstract protected function doExecute(array $params): array;
}
