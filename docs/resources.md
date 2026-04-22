## Ресурсы

Ресурс — это объект, реализующий `quanzo\mcp\interfaces\ResourceInterface`.

Сервер (`quanzo\mcp\classes\Server`) ищет ресурс так:
1) точное совпадение `uri` с тем, что вернул `ResourceInterface::getUri()`
2) если не найдено — перебор ресурсов и проверка `ResourceInterface::matchesUri($requestedUri)`

### Паттерн-ресурсы

`quanzo\mcp\classes\resources\FileResource` поддерживает паттерны (например `file://logs/*`):
- `getUri()` возвращает паттерн
- `matchesUri()` проверяет конкретный URI на соответствие паттерну
- `getContent($requestedUri)` получает конкретный URI и может выбрать конкретный файл

### Нюанс `getContent($requestedUri)`

Если ресурс паттерн-ориентированный, важно использовать именно `$requestedUri`, а не “базовый” `getUri()`.

