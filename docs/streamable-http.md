## Streamable HTTP

Стандартный транспорт MCP: единый endpoint `/mcp`.

Entry points:

- `bin/http_server.php` — PHP built-in server (`php -S`), по умолчанию `127.0.0.1:8080`
- `bin/http_server_amp.php` — Amp

Класс: `quanzo\mcp\classes\transport\StreamableHttpTransport`.

### Методы endpoint

| HTTP | Поведение |
|---|---|
| `POST /mcp` | JSON-RPC request/notification/batch |
| `GET /mcp` | **405** (SSE не реализован — допустимо спецификацией) |
| `DELETE /mcp` | Завершение сессии (`Mcp-Session-Id`) |
| `OPTIONS /mcp` | CORS preflight |
| `GET /health` | Служебный health (не часть MCP) |

REST `/api/*` **не является** MCP и не поддерживается.

### POST

1. `Accept` **обязан** включать и `application/json`, и `text/event-stream` (иначе **406**); пустой Accept → **406**; `*/*` допускается
2. `MCP-Protocol-Version`: если есть и не из поддерживаемых → **400**; отсутствует → default `2025-03-26`
3. Notification / client response → **202**, пустое тело
4. Request → **200** + `application/json` с JSON-RPC response

### Сессии

- На `initialize` → заголовок `Mcp-Session-Id`
- Дальше заголовок обязателен
- Без session → **400**; неизвестная → **404**

### Неверный ввод → HTTP

| Ввод | HTTP |
|---|---|
| Битый / пустой JSON | 400 + JSON-RPC `-32700` |
| Нет/неверный Bearer (если включён) | 401 |
| Плохой Origin | 403 |
| Нет session после init | 400 |
| Неизвестная session | 404 |
| `tools/call` с битыми args | **200** + JSON-RPC `-32602` |

### Безопасность

- Проверка `Origin` (нет Origin или localhost по умолчанию)
- Bind `127.0.0.1`
- Опционально `MCP_HTTP_BEARER` / `-b`

```bash
MCP_ALLOWED_ORIGINS=https://app.example.com php bin/http_server.php -b mytoken
```

### Пример initialize (curl)

```bash
curl -s -D - -X POST http://127.0.0.1:8080/mcp \
  -H 'Accept: application/json, text/event-stream' \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"curl","version":"1.0"}}}'
```

### Конфиг агента

```json
{
  "mcpServers": {
    "quanzo-mcp-http": {
      "url": "http://127.0.0.1:8080/mcp"
    }
  }
}
```
