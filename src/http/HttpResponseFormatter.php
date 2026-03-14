<?php

namespace app\modules\neuron\mcp\http;

/**
 * Форматтер HTTP-ответов для MCP HTTP API
 *
 * Единообразное формирование JSON-ответов (success/error) для MCPHttpServer и MCPHttpServerAmp.
 */
class HttpResponseFormatter
{
    /**
     * Формирует массив успешного ответа
     *
     * @param array<string, mixed>|mixed $data Данные ответа
     * @param string|null $message Опциональное сообщение
     *
     * @return array<string, mixed>
     */
    public static function success($data, ?string $message = null): array
    {
        $out = [
            'status' => 'success',
            'data' => $data,
            'timestamp' => date('c'),
        ];

        if ($message !== null) {
            $out['message'] = $message;
        }

        return $out;
    }

    /**
     * Формирует массив ответа с ошибкой
     *
     * @param int $code HTTP-код ответа
     * @param string $message Сообщение об ошибке
     * @param array<string, mixed> $details Дополнительные данные (например message, trace)
     *
     * @return array<string, mixed>
     */
    public static function error(int $code, string $message, array $details = []): array
    {
        $out = [
            'status' => 'error',
            'code' => $code,
            'message' => $message,
            'timestamp' => date('c'),
        ];

        if ($details !== []) {
            $out['details'] = $details;
        }

        return $out;
    }
}
