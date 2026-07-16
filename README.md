# MCP (Model Context Protocol) — PHP

Стандартная реализация MCP-сервера на PHP 8.1+: JSON-RPC протокол + транспорты **stdio** и **Streamable HTTP**.

Совместимо с Cursor, Claude Code, Codex и другими MCP-клиентами.

## Возможности

- Lifecycle: `initialize` / `notifications/initialized` / `ping`
- Tools: `tools/list`, `tools/call`
- Resources: `resources/list`, `resources/read`
- Валидация аргументов по JSON Schema
- Stdio (newline-delimited JSON)
- Streamable HTTP (единый endpoint `/mcp`)
- PSR-логирование в файл (не в STDOUT)
- Устойчивость к неверному вводу (JSON-RPC error / `isError`, без падения)

## Требования

- PHP 8.1+
- Composer

## Установка

```bash
composer install
mkdir -p logs config data
```

## Stdio (агенты)

```bash
php bin/mcp_server.php
```

Конфиг Cursor / Claude Desktop:

```json
{
  "mcpServers": {
    "quanzo-mcp": {
      "command": "php",
      "args": ["/var/www/mcp/bin/mcp_server.php"]
    }
  }
}
```

## Streamable HTTP

```bash
# По умолчанию 127.0.0.1:8080
php bin/http_server.php

# Amp-вариант
php bin/http_server_amp.php 127.0.0.1 8080
```

Endpoint: `http://127.0.0.1:8080/mcp`

Опционально:

```bash
MCP_HTTP_BEARER=token php bin/http_server.php -b token
```

Конфиг агента (HTTP):

```json
{
  "mcpServers": {
    "quanzo-mcp-http": {
      "url": "http://127.0.0.1:8080/mcp"
    }
  }
}
```

## Быстрая проверка

```bash
php bin/test_client.php
```

## Структура

```
bin/
├── mcp_server.php          # stdio MCP
├── http_server.php         # Streamable HTTP (php -S)
├── http_server_amp.php     # Streamable HTTP (Amp)
└── test_client.php

src/classes/
├── McpServer.php           # протокольное ядро
├── McpServerFactory.php
├── transport/
│   ├── StdioTransport.php
│   ├── StreamableHttpTransport.php
│   └── ...
├── commands/               # tools
├── resources/
├── client/MCPClient.php
└── dto/mcp/                # DTO ответов MCP

docs/
├── overview.md
├── quickstart.md
├── mcp-protocol.md
├── streamable-http.md
├── commands.md
├── resources.md
└── ...
```

## Документация

- Обзор: `docs/overview.md`
- Быстрый старт: `docs/quickstart.md`
- Протокол: `docs/mcp-protocol.md`
- HTTP: `docs/streamable-http.md`
- Нюансы / ошибки: `docs/mcp-nuances.md`
- Команды / ресурсы: `docs/commands.md`, `docs/resources.md`
- Разработка: `docs/development.md`
