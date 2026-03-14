<?php

namespace app\modules\neuron\mcp\interfaces;

/**
 * Интерфейс ResourceInterface
 *
 * Определяет контракт для ресурсов MCP сервера.
 * Ресурсы представляют собой статические или динамически генерируемые данные,
 * доступные для чтения через MCP протокол.
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
     * Возвращает метаданные ресурса
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
