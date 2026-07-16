## MCP протокол

Целевая спецификация: [2025-03-26](https://modelcontextprotocol.io/specification/2025-03-26).  
Negotiation также: `2024-11-05`, `2025-06-18`, `2025-11-25`.

Транспорты: **stdio** и **Streamable HTTP**. Сообщения — JSON-RPC 2.0.

**Кастомный API запрещён:** нет `mcp.listCommands`, нет вызова tool как `method: "echo"`, нет `params.auth`.

Ядро: `quanzo\mcp\classes\McpServer`. Транспорт только доставляет сообщения.

### Lifecycle

1. Client → `initialize`
2. Server → `InitializeResult` (`protocolVersion`, `capabilities`, `serverInfo`)
3. Client → `notifications/initialized` (без ответа)
4. Далее — обычные методы

До `initialize` разрешены только `initialize` и `ping`.

### Методы

| Method | Result |
|---|---|
| `initialize` | `{protocolVersion, capabilities, serverInfo}` |
| `ping` | `{}` |
| `tools/list` | `{tools:[{name, description, inputSchema}]}` |
| `tools/call` | `{content:[{type:"text", text}], isError}` |
| `resources/list` | `{resources:[{uri, name, mimeType?, description?}]}` |
| `resources/read` | `{contents:[{uri, mimeType, text}]}` |

### Ошибки JSON-RPC

| Code | Когда |
|---|---|
| `-32700` | Parse error |
| `-32600` | Invalid Request |
| `-32601` | Method not found |
| `-32602` | Invalid params / unknown tool / schema |
| `-32603` | Internal error (в т.ч. сбой чтения ресурса) |
| `-32002` | Not initialized / resource not found |

Ошибки выполнения tool (бизнес-логика) → **не** JSON-RPC error, а `result.isError: true`.

### Неверный ввод → ожидаемый ответ

| Ввод | Ответ |
|---|---|
| Битый JSON (stdio) | `-32700`, цикл продолжается |
| Unknown method | `-32601` |
| `tools/call` без name / arguments не object | `-32602` |
| Unknown tool | `-32602` |
| Schema validation fail | `-32602` + `validation_errors` |
| Divide by zero в calculate | `isError: true` |
| Resource `getContent` throw | `-32603` |
| Unknown notification | нет ответа |

### Stdio framing

- Одна строка = одно JSON-сообщение
- STDOUT — только MCP messages
- Логи — файл / STDERR

Реализация: `McpServer` + `StdioTransport` (`bin/mcp_server.php`).
