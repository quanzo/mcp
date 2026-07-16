<?php

declare(strict_types=1);

namespace quanzo\mcp\classes\transport;

/**
 * Класс HttpTransportResult
 *
 * Результат обработки HTTP-запроса Streamable HTTP транспортом.
 *
 * Пример использования:
 *   $result = HttpTransportResult::json(200, $payload);
 *   $result->emit();
 */
class HttpTransportResult
{
    /**
     * HTTP status code
     *
     * @var int
     */
    private int $statusCode;

    /**
     * Тело ответа
     *
     * @var string
     */
    private string $body;

    /**
     * Заголовки ответа
     *
     * @var array<string, string>
     */
    private array $headers;

    /**
     * Конструктор HttpTransportResult
     *
     * @param int $statusCode Код статуса
     * @param string $body Тело
     * @param array<string, string> $headers Заголовки
     */
    public function __construct(int $statusCode, string $body = '', array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->headers = $headers;
    }

    /**
     * Создаёт JSON-ответ
     *
     * @param int $statusCode Код
     * @param array<string, mixed>|object $data Данные
     * @param array<string, string> $headers Заголовки
     *
     * @return self
     */
    public static function json(int $statusCode, $data, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'application/json'], $headers);

        return new self(
            $statusCode,
            \quanzo\mcp\helpers\JsonHelper::encode($data),
            $headers
        );
    }

    /**
     * Создаёт текстовый ответ
     *
     * @param int $statusCode Код
     * @param string $text Текст
     * @param array<string, string> $headers Заголовки
     *
     * @return self
     */
    public static function text(int $statusCode, string $text, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'text/plain; charset=utf-8'], $headers);

        return new self($statusCode, $text, $headers);
    }

    /**
     * Создаёт ответ без тела
     *
     * @param int $statusCode Код
     * @param array<string, string> $headers Заголовки
     *
     * @return self
     */
    public static function empty(int $statusCode, array $headers = []): self
    {
        return new self($statusCode, '', $headers);
    }

    /**
     * Возвращает HTTP status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Возвращает тело ответа
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Возвращает заголовки
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Отправляет ответ в текущий PHP SAPI
     *
     * @return void
     */
    public function emit(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
