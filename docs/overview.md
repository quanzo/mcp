## Обзор

`quanzo/mcp` — **стандартная** реализация Model Context Protocol на PHP.

Слои:

1. **Протокол** — `quanzo\mcp\classes\McpServer` (JSON-RPC MCP: initialize, tools/*, resources/*, ping)
2. **Транспорты**:
   - **stdio** — `StdioTransport` (`bin/mcp_server.php`)
   - **Streamable HTTP** — `StreamableHttpTransport` (`bin/http_server.php`, `bin/http_server_amp.php`)

Кастомных методов (`mcp.listCommands`, REST `/api/execute`) **нет и не будет**.

### Компоненты

| Класс / путь | Роль |
|---|---|
| `McpServer` | Протокольное ядро |
| `McpServerFactory` | Сборка демо-сервера с tools/resources |
| `StdioTransport` | newline JSON STDIN/STDOUT |
| `StreamableHttpTransport` | POST/GET/DELETE `/mcp` |
| `McpSessionStore` | HTTP-сессии (`Mcp-Session-Id`) |
| `HttpServerBootstrap` | Общая подготовка HTTP entry points |
| `MCPClient` | stdio-клиент для тестов |
| `src/commands/*` | Tools (`quanzo\mcp\commands`) |
| `src/wiki/*` | Домен wiki/ollama поиска (searchers, loaders, DTO) |
| `src/classes/resources/*` | Resources |
| `src/classes/dto/mcp/*` | DTO wire-формата |

### Куда смотреть

- Быстрый старт: `docs/quickstart.md`
- Протокол + таблица ошибок: `docs/mcp-protocol.md`
- Streamable HTTP: `docs/streamable-http.md`
- Команды: `docs/commands.md`
- Ресурсы: `docs/resources.md`
- Нюансы (`isError`, sessions): `docs/mcp-nuances.md`
- Разработка / resilience / phpdoc: `docs/development.md`
