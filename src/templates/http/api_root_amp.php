<?php

/** @var string $phpVersion */
/** @var int $activeConnections */
/** @var string $memoryUsage */
/** @var string $uptime */
/** @var string $host */
/** @var int $port */
/** @var int $currentYear */

$phpVersion ??= PHP_VERSION;
$activeConnections ??= 0;
$memoryUsage ??= '';
$uptime ??= '';
$host ??= '0.0.0.0';
$port ??= 0;
$currentYear ??= (int) date('Y');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCP HTTP Server (Amp)</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        .header h1 {
            font-size: 3em;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }
        .status {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status-dot {
            width: 12px;
            height: 12px;
            background: #4CAF50;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .endpoints {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .endpoint-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            transition: transform 0.3s, background 0.3s;
        }
        .endpoint-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.2);
        }
        .method {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 5px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .get { background: #4CAF50; }
        .post { background: #2196F3; }
        .path {
            font-family: monospace;
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        .description {
            opacity: 0.9;
            font-size: 0.95em;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.2);
            opacity: 0.7;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9em;
            opacity: 0.8;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🚀 MCP HTTP Server (Amp)</h1>
        <p>Высокопроизводительный HTTP интерфейс для MCP сервера</p>
    </div>

    <div class="status">
        <div class="status-indicator">
            <div class="status-dot"></div>
            <span>Сервер работает</span>
        </div>
        <div>Версия: 1.0.0 | Amp 3.x | PHP <?= htmlspecialchars((string) $phpVersion, ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-value"><?= (int) $activeConnections ?></div>
            <div class="stat-label">Активных соединений</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= htmlspecialchars((string) $memoryUsage, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="stat-label">Использование памяти</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= htmlspecialchars((string) $uptime, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="stat-label">Время работы</div>
        </div>
    </div>

    <h2 style="margin: 30px 0 20px 0; color: #fff;">📡 Доступные эндпоинты</h2>

    <div class="endpoints">
        <div class="endpoint-card">
            <span class="method get">GET</span>
            <div class="path">/api/commands</div>
            <div class="description">Получить список доступных команд MCP</div>
        </div>

        <div class="endpoint-card">
            <span class="method get">GET</span>
            <div class="path">/api/resources</div>
            <div class="description">Получить список доступных ресурсов</div>
        </div>

        <div class="endpoint-card">
            <span class="method post">POST</span>
            <div class="path">/api/execute</div>
            <div class="description">Выполнить MCP команду</div>
        </div>

        <div class="endpoint-card">
            <span class="method get">GET</span>
            <div class="path">/api/health</div>
            <div class="description">Проверка состояния сервера</div>
        </div>

        <div class="endpoint-card">
            <span class="method get">GET</span>
            <div class="path">/api/info</div>
            <div class="description">Подробная информация о сервере</div>
        </div>

        <div class="endpoint-card">
            <span class="method get">GET</span>
            <div class="path">/api/metrics</div>
            <div class="description">Метрики производительности</div>
        </div>
    </div>

    <div class="footer">
        <p>MCP HTTP Server (Amp) v1.0.0</p>
        <p>Адрес сервера: http://<?= htmlspecialchars((string) $host, ENT_QUOTES, 'UTF-8') ?>:<?= (int) $port ?></p>
        <p>© <?= (int) $currentYear ?> MCP Server Team</p>
    </div>
</div>

<script>
    async function updateStats() {
        try {
            const response = await fetch('/api/health');
            const data = await response.json();

            if (data.server) {
                document.querySelector('.stat-value:nth-child(1)').textContent =
                    data.server.active_connections || 0;
            }
        } catch (error) {
            console.error('Failed to update stats:', error);
        }
    }

    setInterval(updateStats, 5000);
    updateStats();
</script>
</body>
</html>

