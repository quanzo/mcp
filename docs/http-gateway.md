## HTTP gateway

HTTP gateway превращает HTTP-запросы в JSON-RPC запросы к MCP через stdio.

### Варианты

- **Sync**: `quanzo\mcp\classes\MCPHttpServer` (`bin/http_server.php`)
- **Amp**: `quanzo\mcp\classes\MCPHttpServerAmp` (`bin/http_server_amp.php`)

Оба варианта создают `quanzo\mcp\classes\client\MCPClient`, который запускает `bin/mcp_server.php` как дочерний процесс и общается с ним по stdio.

### Обязательный `mcpServerScript`

В обоих классах путь к stdio серверу (`mcpServerScript`) передаётся **в конструктор** и проверяется на существование файла (`file_exists`). Если файла нет — будет выброшено исключение.

### Путь к шаблонам (`templatesRoot`)

`MCPHttpServerAmp` принимает **в конструктор** путь к корню шаблонов (`templatesRoot`), обычно это `.../src/templates`. Это позволяет хранить HTML/текст в `src/templates/**` и рендерить его из классов.

### Маршруты

- **GET `/api/commands`** → `mcp.listCommands`
- **GET `/api/resources`** → `mcp.listResources`
- **POST `/api/execute`** → выполнить команду из тела `{ "command": "...", "params": { ... } }`
- **GET `/api/health`**, **GET `/api/info`** → сервисные ответы самого gateway

### Авторизация

Если в `params` не передан `auth`, gateway добавляет свой `$authKey` перед отправкой в MCP.

### Заголовки

Есть дефолтный набор заголовков (JSON + CORS/кеш), и вы можете переопределить/добавить свои:

- `setHeaders(array $headers)` — мердж поверх дефолтов
- `addHeader(string $name, string $value)` — добавить/переопределить один заголовок

