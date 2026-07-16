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
    /**
     * Возвращает URI ресурса
     *
     * @return string
     */
    public function getUri(): string
    {
        return 'myresource://data';
    }

    /**
     * Возвращает имя ресурса
     *
     * @return string
     */
    public function getName(): string
    {
        return 'my_resource_data';
    }

    /**
     * Возвращает описание ресурса
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return 'Пример статического JSON-ресурса';
    }

    /**
     * Возвращает MIME-тип содержимого
     *
     * @return string
     */
    public function getMimeType(): string
    {
        return 'application/json';
    }

    /**
     * Возвращает содержимое ресурса
     *
     * @param string|null $requestedUri Запрошенный URI
     *
     * @return string
     */
    public function getContent(?string $requestedUri = null): string
    {
        return JsonHelper::encode(['data' => 'Пример данных'], JsonHelper::DEFAULT_PRETTY_FLAGS);
    }

    /**
     * Возвращает внутренние метаданные
     *
     * @return array<string, string>
     */
    public function getMetadata(): array
    {
        return ['type' => 'custom'];
    }

    /**
     * Проверяет соответствие URI
     *
     * @param string $uri URI
     *
     * @return bool
     */
    public function matchesUri(string $uri): bool
    {
        return $uri === $this->getUri();
    }
}
