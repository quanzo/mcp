<?php

namespace quanzo\mcp\resources;

use quanzo\mcp\interfaces\ResourceInterface;

/**
 * Класс MyResource
 *
 * Пример статического ресурса с фиксированным URI и JSON-содержимым.
 */
class MyResource implements ResourceInterface
{
    public function getUri(): string
    {
        return 'myresource://data';
    }

    public function getMimeType(): string
    {
        return 'application/json';
    }

    public function getContent(?string $requestedUri = null): string
    {
        return json_encode(['data' => 'Пример данных']);
    }

    public function getMetadata(): array
    {
        return ['type' => 'custom'];
    }

    public function matchesUri(string $uri): bool
    {
        return $uri === $this->getUri();
    }
}
