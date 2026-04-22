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

