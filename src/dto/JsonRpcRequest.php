<?php

namespace app\modules\neuron\mcp\dto;

/**
 * DTO входящего запроса JSON-RPC 2.0
 *
 * Инкапсулирует поля запроса: id, method, params.
 * Устраняет использование «магических» ключей массива в коде.
 */
class JsonRpcRequest
{
    /**
     * Идентификатор запроса (может быть null для уведомлений)
     *
     * @var string|int|null
     */
    private $id;

    /**
     * Имя метода
     */
    private string $method;

    /**
     * Параметры запроса
     *
     * @var array<string, mixed>
     */
    private array $params;

    /**
     * Создаёт DTO из массива запроса
     *
     * @param array<string, mixed> $data Десериализованный JSON-RPC запрос
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->id = $data['id'] ?? null;
        $request->method = $data['method'] ?? '';
        $request->params = $data['params'] ?? [];

        return $request;
    }

    /**
     * Возвращает идентификатор запроса
     *
     * @return string|int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Возвращает имя метода
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Возвращает параметры запроса
     *
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
