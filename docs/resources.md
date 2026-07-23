## Ресурсы

Ресурс — `quanzo\mcp\interfaces\ResourceInterface`.

Протокол:

- `resources/list` → `{uri, name, mimeType?, description?}`
- `resources/read` → `{contents:[{uri, mimeType, text}]}`

### Обязательные методы

- `getUri()`, `getName()`, `getDescription()`, `getMimeType()`
- `getContent(?string $requestedUri)`
- `matchesUri(string $uri)` — для паттернов

Демо-ресурсы регистрируются в `McpServerFactory`.

### FileResource

```php
new FileResource(
    'file://logs/mcp-server.log',
    'text/plain',
    $projectRoot . '/logs',
    'mcp_server_log',
    'Лог-файл'
);
```

Паттерн `*` в URI поддерживается через `matchesUri()`.  
Отсутствие файла → исключение на уровне ресурса; через `McpServer` это становится JSON-RPC `-32603` (без падения процесса).
