## Команды (tools)

Команда — `quanzo\mcp\interfaces\CommandInterface`, обычно через `BaseCommand`.

Вызов из протокола: `tools/call` с `{ "name": "...", "arguments": { ... } }`.

### Регистрация

Демо-tools регистрируются в `McpServerFactory::createDefault()`.  
Свои:

```php
$server->registerCommand(new YourCommand());
```

Имя tool — стабильный `snake_case` (`user_create`, не `user.create`).

### Валидация

`JsonSchemaValidator` + `getInputSchema()`.  
`additionalProperties: false` запрещает лишние ключи.  
`pattern` — полное совпадение строки.

Ошибка схемы → JSON-RPC `-32602`.  
Runtime внутри `doExecute` → `isError: true` в result.
