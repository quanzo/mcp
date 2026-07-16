<?php

declare(strict_types=1);

namespace quanzo\mcp\classes\dto\mcp;

/**
 * DTO ServerInfo
 *
 * Идентификация MCP-сервера для ответа initialize (serverInfo).
 *
 * Пример использования:
 *   $info = new ServerInfo('quanzo-mcp', '1.0.0');
 *   $array = $info->toArray();
 */
class ServerInfo
{
    /**
     * Имя сервера
     *
     * @var string
     */
    private string $name;

    /**
     * Версия сервера
     *
     * @var string
     */
    private string $version;

    /**
     * Конструктор ServerInfo
     *
     * @param string $name Имя сервера
     * @param string $version Версия сервера
     */
    public function __construct(string $name, string $version)
    {
        $this->name = $name;
        $this->version = $version;
    }

    /**
     * Возвращает имя сервера
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Возвращает версию сервера
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Сериализует DTO в массив для JSON-RPC
     *
     * @return array{name: string, version: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
        ];
    }
}
