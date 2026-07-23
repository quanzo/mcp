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
Подмножество: type, required, enum, min/max, minLength/maxLength, **minItems/maxItems**, pattern, properties, items, `additionalProperties: false`.  
`pattern` — полное совпадение строки.

Ошибка схемы → JSON-RPC `-32602`.  
Runtime/business внутри `doExecute` (в т.ч. занятый email в `UserCommand`) → `isError: true` в result.

Каталог tools: `src/commands/` (`quanzo\mcp\commands`).
