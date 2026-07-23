<?php

declare(strict_types=1);

namespace quanzo\mcp\helpers;

/**
 * JsonHelper
 *
 * Централизует encode/decode JSON для всего проекта:
 * единые флаги, JSON_THROW_ON_ERROR, без дублирования.
 *
 * Пример использования:
 *   $json = JsonHelper::encode($data);
 *   $arr = JsonHelper::decode($json, true);
 */
final class JsonHelper
{
    public const int DEFAULT_PRETTY_FLAGS =
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES;

    public const int DEFAULT_COMPACT_FLAGS =
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES;

    /**
     * Кодирует данные в JSON.
     *
     * @param mixed $data
     * @param int $flags JSON_* flags (без `JSON_THROW_ON_ERROR` — он добавляется автоматически).
     */
    public static function encode($data, int $flags = self::DEFAULT_COMPACT_FLAGS): string
    {
        return json_encode($data, $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * Декодирует JSON строку.
     *
     * @return mixed
     */
    public static function decode(string $json, bool $assoc = true)
    {
        return json_decode($json, $assoc, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Декодирует JSON в ассоциативный массив (пустой массив при ошибке/null)
     *
     * @param string $json JSON-строка
     *
     * @return array<mixed>
     */
    public static function decodeAssociative(string $json): array
    {
        try {
            $decoded = self::decode($json, true);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Кодирует данные в JSON (синоним encode для совместимости wiki-домена)
     *
     * @param mixed $data Данные
     *
     * @return string
     */
    public static function encodeThrow($data): string
    {
        return self::encode($data);
    }
}
