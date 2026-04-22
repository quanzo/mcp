## Разработка

### Проверки качества

Из корня проекта:

```bash
composer dump-autoload
./vendor/bin/phpcs
./vendor/bin/phpstan analyse
./vendor/bin/phpunit
```

### Частые проблемы

- **Тесты “висят”**: почти всегда это отсутствие перевода строки в stdio-обмене (см. `docs/mcp-protocol.md` и `docs/mcp-nuances.md`).
- **Class not found**: проверьте соответствие FQCN ↔ путь по PSR-4 и пересоберите autoload: `composer dump-autoload`.
- **Валидация падает “неожиданно”**: `JsonSchemaValidator` оборачивает `pattern` в якоря (полное совпадение), и при `additionalProperties: false` лишние поля запрещены.
- **Разный JSON в разных местах**: используйте `quanzo\mcp\helpers\JsonHelper` вместо прямых `json_encode/json_decode`, чтобы флаги и обработка ошибок были единообразны.
- **HTML/текст сложно поддерживать в heredoc**: выносите в `src/templates/` и рендерите через `quanzo\mcp\helpers\TemplateRenderer`.

