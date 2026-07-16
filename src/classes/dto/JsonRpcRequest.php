<?php

declare(strict_types=1);

namespace quanzo\mcp\classes\dto;

/**
 * DTO входящего запроса JSON-RPC 2.0
 *
 * Инкапсулирует поля запроса: id, method, params.
 * Различает request (есть id) и notification (id отсутствует).
 *
 * Пример использования:
 *   $rpc = JsonRpcRequest::fromArray($data);
 *   if ($rpc->isNotification()) { ... }
 */
class JsonRpcRequest
{
    /**
     * Идентификатор запроса (может быть null даже при наличии поля id)
     *
     * @var string|int|null
     */
    private $id;

    /**
     * Признак наличия поля id в исходном сообщении
     *
     * @var bool
     */
    private bool $hasId = false;

    /**
     * Имя метода
     *
     * @var string
     */
    private string $method = '';

    /**
     * Параметры запроса
     *
     * @var array<string, mixed>
     */
    private array $params = [];

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
        $request->hasId = array_key_exists('id', $data);
        $request->id = $data['id'] ?? null;
        $request->method = isset($data['method']) && is_string($data['method'])
            ? $data['method']
            : '';
        $params = $data['params'] ?? [];
        $request->params = is_array($params) ? $params : [];

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
     * Проверяет, является ли сообщение notification (без поля id)
     *
     * @return bool
     */
    public function isNotification(): bool
    {
        return !$this->hasId;
    }

    /**
     * Возвращает имя метода
     *
     * @return string
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
