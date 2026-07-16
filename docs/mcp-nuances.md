## Нюансы реализации

### Протокол vs транспорт

`McpServer::handleMessage()` не знает про stdio/HTTP.  
Транспорт только доставляет JSON-RPC и оформляет framing/HTTP status.

Смотреть:

- протокол → `src/classes/McpServer.php`
- stdio → `src/classes/transport/StdioTransport.php`
- HTTP → `src/classes/transport/StreamableHttpTransport.php`

### Tools и isError

`CommandInterface::execute()` возвращает `array`.  
`McpServer` оборачивает в:

```json
{
  "content": [{"type": "text", "text": "{...json...}"}],
  "isError": false
}
```

| Ситуация | Ответ |
|---|---|
| Ошибка схемы / unknown tool | JSON-RPC `-32602` |
| Runtime tool (например деление на ноль) | `result.isError: true` |
| Непойманный сбой вне tool | `-32603` |

### Notifications

Сообщения без поля `id` — notifications: ответ не отправляется (stdio) / HTTP **202**.

### Capabilities

Пустой object `{}` (не `[]`).  
При наличии tools/resources: `tools: {}` и/или `resources: {}`.

### HTTP-сессии

Каждая сессия — отдельный `McpServer` (`createSessionInstance()`), общий registry tools/resources, свой lifecycle.

### Имена tools

Только `snake_case` (`user_create`), не точки (`user.create`).

### Auth

В JSON-RPC **нет** `params.auth`.  
HTTP: опционально `Authorization: Bearer` на транспортном уровне.

### Запрет кастома

Не использовать и не документировать как MCP: `mcp.*` методы, REST `/api/commands`, `/api/execute`.
