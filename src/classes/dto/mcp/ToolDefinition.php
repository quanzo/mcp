<?php

declare(strict_types=1);

namespace quanzo\mcp\classes\dto\mcp;

/**
 * DTO ToolDefinition
 *
 * Описание инструмента для ответа tools/list.
 *
 * Пример использования:
 *   $tool = new ToolDefinition('echo', 'Echo text', $schema);
 */
class ToolDefinition
{
    /**
     * Уникальное имя инструмента
     *
     * @var string
     */
    private string $name;

    /**
     * Человекочитаемое описание
     *
     * @var string
     */
    private string $description;

    /**
     * JSON Schema входных аргументов
     *
     * @var array<string, mixed>
     */
    private array $inputSchema;

    /**
     * Конструктор ToolDefinition
     *
     * @param string $name Имя инструмента
     * @param string $description Описание
     * @param array<string, mixed> $inputSchema JSON Schema аргументов
     */
    public function __construct(string $name, string $description, array $inputSchema)
    {
        $this->name = $name;
        $this->description = $description;
        $this->inputSchema = $inputSchema;
    }

    /**
     * Сериализует определение инструмента в массив
     *
     * @return array{name: string, description: string, inputSchema: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema,
        ];
    }
}
