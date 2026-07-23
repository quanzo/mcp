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

### Поиск (wiki / ollama)

Домен: `src/wiki/` (`quanzo\mcp\wiki\…`) — searchers, loaders, DTO; не смешивать с протоколом.

| Tool name | Класс | Источник |
|---|---|---|
| `wiki_search` | `WikiSearchCommand` | Wikipedia ru+en |
| `ru_wiki_search` | `RuWikiSearchCommand` | RuWiki |
| `uni_search` | `UniSearchCommand` | по умолчанию Wikipedia ru+en |
| `ollama_search` | `OllamaSearchCommand` | Ollama Web Search |

Аргумент: `{ "search": "…" }` (string, minLength 1).  
Результат: `{ "articles": [ { "title", "content" }, … ] }`.

`MCP_OLLAMA_API_KEY` — опционален; запрос без ключа допускается.
