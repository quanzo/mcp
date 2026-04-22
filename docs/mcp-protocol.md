## MCP протокол в этом репозитории

Эта реализация использует **JSON-RPC 2.0** поверх:
- **stdio** (основной режим): `bin/mcp_server.php`
- **HTTP gateway** (прокси): `bin/http_server.php`, `bin/http_server_amp.php`

### Формат JSON-RPC

Запрос:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "mcp.listCommands",
  "params": { "auth": "secret" }
}
```

Ответ:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": { "commands": [] }
}
```

Ошибка:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "error": { "code": -32602, "message": "Invalid parameters" }
}
```

### MCP-методы vs tools-команды

`quanzo\mcp\classes\Server` различает:
- **MCP-методы**: `mcp.listCommands`, `mcp.listResources`, `mcp.readResource`
- **Команды**: всё остальное трактуется как “выполнить команду с именем = method”

### Авторизация через `params.auth`

Если сервер запущен с ключом авторизации:
- клиент **должен** передавать `params.auth`
- при успехе сервер **удаляет** `auth` из массива параметров перед передачей в `CommandInterface::execute()`

Это означает, что схема параметров команды (`getInputSchema()`) не обязана описывать `auth`.

### Stdio: “одна строка — один JSON”

Сервер читает вход через `fgets(STDIN)`, поэтому:
- запрос должен заканчиваться переводом строки
- ответы сервера тоже построчные (в конце всегда перевод строки)

Если клиент пишет JSON без перевода строки — сервер будет ждать завершения строки.

