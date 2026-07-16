<?php

namespace quanzo\mcp\interfaces;

/**
 * Интерфейс ResourceInterface
 *
 * Определяет контракт для ресурсов MCP сервера.
 * Ресурсы представляют собой статические или динамически генерируемые данные,
 * доступные для чтения через MCP протокол (resources/list, resources/read).
 */
interface ResourceInterface
{
    /**
     * Возвращает URI ресурса
     *
     * @return string URI ресурса
     */
    public function getUri(): string;

    /**
     * Возвращает человекочитаемое имя ресурса
     *
     * @return string Имя ресурса
     */
    public function getName(): string;

    /**
     * Возвращает описание ресурса
     *
     * @return string|null Описание или null
     */
    public function getDescription(): ?string;

    /**
     * Возвращает MIME-тип содержимого ресурса
     *
     * @return string MIME-тип (например, 'text/plain', 'application/json')
     */
    public function getMimeType(): string;

    /**
     * Возвращает содержимое ресурса
     *
     * @param string|null $requestedUri Запрошенный URI (для ресурсов по паттерну — конкретный URI запроса)
     *
     * @return string Содержимое ресурса в виде строки
     *
     * @throws \RuntimeException Если невозможно получить содержимое ресурса
     */
    public function getContent(?string $requestedUri = null): string;

    /**
     * Возвращает метаданные ресурса (внутренние, не часть wire-формата MCP)
     *
     * @return array Ассоциативный массив метаданных ресурса
     */
    public function getMetadata(): array;

    /**
     * Проверяет, соответствует ли указанный URI данному ресурсу
     *
     * @param string $uri URI для проверки
     *
     * @return bool true если URI соответствует ресурсу, false в противном случае
     */
    public function matchesUri(string $uri): bool;
}
