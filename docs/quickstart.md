## Быстрый старт

### Stdio

```bash
composer install
php bin/mcp_server.php
```

В другом терминале:

```bash
php bin/test_client.php
```

### Подключение агента (Cursor)

```json
{
  "mcpServers": {
    "quanzo-mcp": {
      "command": "php",
      "args": ["/absolute/path/to/mcp/bin/mcp_server.php"]
    }
  }
}
```

### Streamable HTTP

```bash
php bin/http_server.php -h 127.0.0.1 -p 8080
```

Endpoint: `http://127.0.0.1:8080/mcp`

### Демо-tools

| Name | Описание |
|---|---|
| `echo` | Эхо сообщения |
| `calculate` | Арифметика |
| `user_create` | Создание пользователя |
| `my_command` | Пример кастомной команды |

### Пример ошибки (ожидаемое поведение)

Вызов `calculate` с `b: 0` возвращает MCP result с `isError: true` (процесс не падает).  
Битый JSON на stdio → ответ `-32700`, сервер продолжает слушать.
