<?php

declare(strict_types=1);

namespace quanzo\mcp\classes\dto\mcp;

/**
 * DTO InitializeResult
 *
 * Результат MCP-метода initialize: версия протокола, capabilities и serverInfo.
 *
 * Пример использования:
 *   $result = new InitializeResult('2025-03-26', $capabilities, $serverInfo);
 *   return JsonRpcResponse::success($id, $result->toArray());
 */
class InitializeResult
{
    /**
     * Согласованная версия протокола MCP
     *
     * @var string
     */
    private string $protocolVersion;

    /**
     * Объявленные возможности сервера (объект для JSON {})
     *
     * @var object
     */
    private object $capabilities;

    /**
     * Информация о сервере
     *
     * @var ServerInfo
     */
    private ServerInfo $serverInfo;

    /**
     * Конструктор InitializeResult
     *
     * @param string $protocolVersion Версия протокола
     * @param object $capabilities Capabilities сервера
     * @param ServerInfo $serverInfo Идентификация сервера
     */
    public function __construct(string $protocolVersion, object $capabilities, ServerInfo $serverInfo)
    {
        $this->protocolVersion = $protocolVersion;
        $this->capabilities = $capabilities;
        $this->serverInfo = $serverInfo;
    }

    /**
     * Сериализует результат initialize в массив
     *
     * @return array{protocolVersion: string, capabilities: object, serverInfo: array{name: string, version: string}}
     */
    public function toArray(): array
    {
        return [
            'protocolVersion' => $this->protocolVersion,
            'capabilities' => $this->capabilities,
            'serverInfo' => $this->serverInfo->toArray(),
        ];
    }
}
