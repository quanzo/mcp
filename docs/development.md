## Разработка

### Проверки

```bash
./vendor/bin/phpcs
./vendor/bin/phpstan analyse
./vendor/bin/phpunit
```

### Архитектура при изменениях

1. Протокольные изменения — только `McpServer` + DTO в `dto/mcp/`
2. Framing — `StdioTransport` / `StreamableHttpTransport`
3. Tools — `src/commands/` (`quanzo\mcp\commands`)
4. HTTP bootstrap — `HttpServerBootstrap` (общий для `bin/http_server*.php`)
5. **Не добавлять** кастомные JSON-RPC методы и REST «обёртки» над MCP (`mcp.listCommands`, `/api/execute` и т.п.)

### Resilience-тесты

Обязательно покрывать:

- граничные значения (пустые строки, min/max, `id: null`);
- заведомо неверные данные (кривые типы, битый JSON, unknown tool, divide-by-zero);
- ожидание: JSON-RPC error / `isError: true` / HTTP 4xx — **без uncaught и без падения процесса**;
- после ошибки следующий валидный запрос должен обрабатываться.

Ключевые файлы: `tests/Unit/McpServerTest.php`, `tests/Unit/Transport/*`, `tests/Unit/Commands/JsonSchemaValidatorTest.php`, `tests/Integration/*`.

Минимум 10 кейсов в data-driven наборах валидации; каждый test-метод с русским комментарием.

### PHPDoc (AGENTS.md)

- класс: заголовок, описание, пример использования;
- свойство: русский комментарий + `@var`;
- метод: назначение, `@param` / `@return` / `@throws`;
- нетривиальная логика (lifecycle, sessions, framing, `isError`) — поясняющие комментарии «почему».

### Документация

После изменений обновляйте `docs/` — справочник для агентов. Карта: `docs/overview.md`.
