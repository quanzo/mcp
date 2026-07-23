<?php

namespace quanzo\mcp\classes\dto;

/**
 * DTO ответа JSON-RPC 2.0
 *
 * Представляет успешный ответ (result) или ответ с ошибкой (code, message, data).
 * Позволяет единообразно формировать ответы и сериализовать в массив.
 */
class JsonRpcResponse
{
    /**
     * Идентификатор запроса
     *
     * @var string|int|null
     */
    private $id;

    /**
     * Результат (при успехе)
     *
     * @var array<string, mixed>|mixed
     */
    private $result = null;

    /**
     * Код ошибки (при ошибке)
     */
    private ?int $errorCode = null;

    /**
     * Сообщение об ошибке
     */
    private ?string $errorMessage = null;

    /**
     * Дополнительные данные об ошибке (строка или массив)
     *
     * @var string|array|null
     */
    private $errorData = null;

    /**
     * Создаёт успешный ответ
     *
     * @param string|int|null $id Идентификатор запроса
     * @param array<string, mixed>|mixed $result Результат выполнения
     *
     * @return self
     */
    public static function success($id, $result): self
    {
        $response = new self();
        $response->id = $id;
        $response->result = $result;

        return $response;
    }

    /**
     * Создаёт ответ с ошибкой
     *
     * @param string|int|null $id Идентификатор запроса
     * @param int $code Код ошибки
     * @param string $message Сообщение об ошибке
     * @param string|array|null $data Дополнительные данные
     *
     * @return self
     */
    public static function error($id, int $code, string $message, $data = null): self
    {
        $response = new self();
        $response->id = $id;
        $response->errorCode = $code;
        $response->errorMessage = $message;
        $response->errorData = $data;

        return $response;
    }

    /**
     * Создаёт ответ JSON-RPC Parse error (-32700)
     *
     * @param string|null $data Детали ошибки разбора
     *
     * @return array<string, mixed>
     */
    public static function parseError(?string $data = null): array
    {
        return self::error(null, -32700, 'Parse error', $data)->toArray();
    }

    /**
     * Преобразует ответ в массив для json_encode
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'jsonrpc' => '2.0',
            'id' => $this->id,
        ];

        if ($this->errorCode !== null) {
            $out['error'] = [
                'code' => $this->errorCode,
                'message' => $this->errorMessage,
            ];
            if ($this->errorData !== null && $this->errorData !== '') {
                $out['error']['data'] = $this->errorData;
            }
        } else {
            $out['result'] = $this->result;
        }

        return $out;
    }
}
