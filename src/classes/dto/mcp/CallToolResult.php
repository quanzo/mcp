<?php

declare(strict_types=1);

namespace quanzo\mcp\classes\dto\mcp;

/**
 * DTO CallToolResult
 *
 * Результат MCP-метода tools/call: массив content-блоков и флаг isError.
 *
 * Пример использования:
 *   $result = CallToolResult::fromData(['ok' => true]);
 *   $result = CallToolResult::errorText('Tool failed');
 */
class CallToolResult
{
    /**
     * Блоки контента ответа (text/image/...)
     *
     * @var list<array<string, mixed>>
     */
    private array $content;

    /**
     * Признак ошибки выполнения инструмента (не JSON-RPC error)
     *
     * @var bool
     */
    private bool $isError;

    /**
     * Конструктор CallToolResult
     *
     * @param list<array<string, mixed>> $content Блоки контента
     * @param bool $isError Флаг ошибки выполнения
     */
    public function __construct(array $content, bool $isError = false)
    {
        $this->content = $content;
        $this->isError = $isError;
    }

    /**
     * Создаёт успешный результат из произвольных данных (JSON text content)
     *
     * @param mixed $data Данные для сериализации в text
     * @param callable(mixed): string $encoder Кодировщик в строку
     *
     * @return self
     */
    public static function fromData($data, callable $encoder): self
    {
        return new self(
            [
                [
                    'type' => 'text',
                    'text' => $encoder($data),
                ],
            ],
            false
        );
    }

    /**
     * Создаёт результат с ошибкой выполнения инструмента
     *
     * @param string $message Текст ошибки
     *
     * @return self
     */
    public static function errorText(string $message): self
    {
        return new self(
            [
                [
                    'type' => 'text',
                    'text' => $message,
                ],
            ],
            true
        );
    }

    /**
     * Сериализует результат tools/call в массив
     *
     * @return array{content: list<array<string, mixed>>, isError: bool}
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'isError' => $this->isError,
        ];
    }
}
