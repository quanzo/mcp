## Quickstart

### Установка

```bash
composer install
```

### Запуск MCP (stdio)

```bash
php bin/mcp_server.php
```

Ключ авторизации берётся из переменной окружения `MCP_AUTH_KEY` (или используется дефолт внутри скрипта).

### Запуск HTTP gateway (sync, встроенный php -S)

```bash
php bin/http_server.php --port=8080 --host=0.0.0.0 --key=my-secret-key
```

### Запуск HTTP gateway (Amp)

```bash
php bin/http_server_amp.php 0.0.0.0 8080 my-secret-key
```

### Демо-клиент

```bash
php bin/test_client.php
```

### Минимальный JSON-RPC запрос (stdio)

Один запрос — одна строка JSON (обязательно заканчивается переводом строки).

```json
{"jsonrpc":"2.0","id":1,"method":"mcp.listCommands","params":{"auth":"your-secret-key"}}
```

