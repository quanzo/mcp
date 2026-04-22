## Команды (tools)

Команда — это класс, реализующий `quanzo\mcp\interfaces\CommandInterface`.

Обычно команды наследуются от `quanzo\mcp\classes\commands\BaseCommand`, который даёт стандартный пайплайн:
- `execute($params)` → `validateInput($params)` → `doExecute($params)`

### JSON Schema валидация

Валидация выполняется через `quanzo\mcp\classes\validation\JsonSchemaValidator`.

Нюансы:
- `additionalProperties: false` запрещает любые лишние ключи.
- `pattern` интерпретируется как “полное совпадение” (валидатор оборачивает regex якорями).

### Добавить свою команду

1) Создайте класс в `src/classes/commands/` с неймспейсом `quanzo\mcp\classes\commands`.

2) Зарегистрируйте команду в `bin/mcp_server.php` через:

```php
$server->registerCommand(new YourCommand());
```

