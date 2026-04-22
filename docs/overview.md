## Обзор

`quanzo/mcp` — реализация MCP-сервера на PHP, который общается с клиентом поверх **JSON-RPC 2.0**.

В этом репозитории есть два способа доступа:
- **stdio MCP server**: процесс читает запросы из `STDIN` и пишет ответы в `STDOUT` (одна строка = один JSON).
- **HTTP gateway**: принимает HTTP-запросы и проксирует их в stdio через запуск `bin/mcp_server.php` как дочернего процесса.

### Компоненты

- **Сервер**: `quanzo\mcp\classes\Server` (`src/classes/Server.php`)
- **Команды (tools)**: `quanzo\mcp\classes\commands\*` (`src/classes/commands/`)
- **Ресурсы**: `quanzo\mcp\classes\resources\*` (`src/classes/resources/`)
- **Клиент stdio**: `quanzo\mcp\classes\client\MCPClient` (`src/classes/client/MCPClient.php`)
- **HTTP gateway (sync)**: `quanzo\mcp\classes\MCPHttpServer` (`src/classes/MCPHttpServer.php`)
- **HTTP gateway (Amp)**: `quanzo\mcp\classes\MCPHttpServerAmp` (`src/classes/MCPHttpServerAmp.php`)
- **JSON helper**: `quanzo\mcp\helpers\JsonHelper` (`src/helpers/JsonHelper.php`)
- **Template renderer**: `quanzo\mcp\helpers\TemplateRenderer` (`src/helpers/TemplateRenderer.php`)
- **Шаблоны**: `src/templates/`

### Куда смотреть дальше

- Быстрый старт: `docs/quickstart.md`
- Разработка и проверки: `docs/development.md`
- Протокол MCP/JSON-RPC: `docs/mcp-protocol.md`
- HTTP gateway: `docs/http-gateway.md`
- Команды: `docs/commands.md`
- Ресурсы: `docs/resources.md`
- Глубокие нюансы/edge cases: `docs/mcp-nuances.md`

