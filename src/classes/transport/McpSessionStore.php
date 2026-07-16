<?php

declare(strict_types=1);

namespace quanzo\mcp\classes\transport;

use quanzo\mcp\classes\McpServer;

/**
 * Класс McpSessionStore
 *
 * Хранилище HTTP-сессий MCP: session id → экземпляр McpServer.
 *
 * Пример использования:
 *   $store = new McpSessionStore($templateServer);
 *   $id = $store->createSession();
 *   $server = $store->get($id);
 */
class McpSessionStore
{
    /**
     * Шаблон сервера (tools/resources)
     *
     * @var McpServer
     */
    private McpServer $template;

    /**
     * Активные сессии
     *
     * @var array<string, McpServer>
     */
    private array $sessions = [];

    /**
     * Конструктор McpSessionStore
     *
     * @param McpServer $template Шаблон с зарегистрированными tools/resources
     */
    public function __construct(McpServer $template)
    {
        $this->template = $template;
    }

    /**
     * Создаёт новую сессию и возвращает её идентификатор
     *
     * @return string Session id
     */
    public function createSession(): string
    {
        $id = $this->generateSessionId();
        $this->sessions[$id] = $this->template->createSessionInstance();

        return $id;
    }

    /**
     * Возвращает сервер сессии
     *
     * @param string $sessionId Идентификатор сессии
     *
     * @return McpServer|null
     */
    public function get(string $sessionId): ?McpServer
    {
        return $this->sessions[$sessionId] ?? null;
    }

    /**
     * Удаляет сессию
     *
     * @param string $sessionId Идентификатор сессии
     *
     * @return bool true если сессия существовала
     */
    public function delete(string $sessionId): bool
    {
        if (!isset($this->sessions[$sessionId])) {
            return false;
        }

        unset($this->sessions[$sessionId]);

        return true;
    }

    /**
     * Генерирует криптостойкий session id (видимые ASCII 0x21-0x7E)
     *
     * @return string
     */
    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
