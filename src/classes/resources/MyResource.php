<?php

namespace quanzo\mcp\classes\resources;

use quanzo\mcp\interfaces\ResourceInterface;
use quanzo\mcp\helpers\JsonHelper;

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
        return JsonHelper::encode(['data' => 'Пример данных'], JsonHelper::DEFAULT_PRETTY_FLAGS);
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
