<?php

declare(strict_types=1);

namespace quanzo\mcp\classes\dto\mcp;

/**
 * DTO ReadResourceResult
 *
 * Результат MCP-метода resources/read: массив contents.
 *
 * Пример использования:
 *   $result = ReadResourceResult::text('file://a', 'text/plain', 'hello');
 */
class ReadResourceResult
{
    /**
     * Содержимое ресурсов
     *
     * @var list<array<string, mixed>>
     */
    private array $contents;

    /**
     * Конструктор ReadResourceResult
     *
     * @param list<array<string, mixed>> $contents Элементы contents
     */
    public function __construct(array $contents)
    {
        $this->contents = $contents;
    }

    /**
     * Создаёт результат с текстовым содержимым
     *
     * @param string $uri URI ресурса
     * @param string $mimeType MIME-тип
     * @param string $text Текст содержимого
     *
     * @return self
     */
    public static function text(string $uri, string $mimeType, string $text): self
    {
        return new self([
            [
                'uri' => $uri,
                'mimeType' => $mimeType,
                'text' => $text,
            ],
        ]);
    }

    /**
     * Сериализует результат resources/read в массив
     *
     * @return array{contents: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'contents' => $this->contents,
        ];
    }
}
