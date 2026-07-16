<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>quanzo-mcp</title>
</head>
<body>
    <h1>quanzo-mcp</h1>
    <p>Host: <?= htmlspecialchars((string) ($host ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <p>Port: <?= htmlspecialchars((string) ($port ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <p>Standard MCP Streamable HTTP endpoint: <code>/mcp</code></p>
</body>
</html>
