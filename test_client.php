<?php
/**
 * Клиент для тестирования MCP сервера (test_client.php)
 * 
 * Демонстрирует:
 * 1. Запуск MCP сервера как дочернего процесса
 * 2. Отправку запросов через STDIN
 * 3. Чтение ответов через STDOUT
 * 4. Обработку ошибок и валидации
 */

require_once __DIR__ . '/vendor/autoload.php';

use app\modules\neuron\mcp\client\MCPClient;

/**
 * Основной скрипт тестирования
 */
try {
    echo "=== Тестирование MCP сервера ===\n\n";
    
    // Инициализация клиента
    $client = new MCPClient(__DIR__ . '/mcp_server.php', 'default-secret-key-123');
    
    // Тест 1: Получение списка команд
    echo "1. Получение списка команд:\n";
    $commands = $client->listCommands();
    foreach ($commands as $command) {
        echo "   - {$command['name']}: {$command['description']}\n";
    }
    echo "\n";
    
    // Тест 2: Получение списка ресурсов
    echo "2. Получение списка ресурсов:\n";
    $resources = $client->listResources();
    foreach ($resources as $resource) {
        echo "   - {$resource['uri']} ({$resource['mimeType']})\n";
    }
    echo "\n";
    
    // Тест 3: Выполнение команды echo
    echo "3. Тестирование команды echo:\n";
    $echoResponse = $client->sendRequest('echo', ['message' => 'Привет, MCP сервер!']);
    if (isset($echoResponse['result'])) {
        echo "   Результат: " . json_encode($echoResponse['result'], JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "   Ошибка: " . json_encode($echoResponse['error'], JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";
    
    // Тест 4: Выполнение команды calculate
    echo "4. Тестирование команды calculate:\n";
    $calcResponse = $client->sendRequest('calculate', [
        'operation' => 'add',
        'a' => 15,
        'b' => 27
    ]);
    if (isset($calcResponse['result'])) {
        echo "   Результат: {$calcResponse['result']['result']}\n";
        echo "   Выражение: {$calcResponse['result']['expression']}\n";
    } else {
        echo "   Ошибка: " . json_encode($calcResponse['error'], JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";
    
    // Тест 5: Выполнение команды user.create с валидными данными
    echo "5. Тестирование команды user.create (валидные данные):\n";
    $userResponse = $client->sendRequest('user.create', [
        'name' => 'Иван Петров',
        'email' => 'ivan@example.com',
        'age' => 30,
        'roles' => ['user', 'admin']
    ]);
    if (isset($userResponse['result'])) {
        echo "   Пользователь создан:\n";
        echo "   - ID: {$userResponse['result']['id']}\n";
        echo "   - Имя: {$userResponse['result']['name']}\n";
        echo "   - Email: {$userResponse['result']['email']}\n";
        echo "   - Статус: {$userResponse['result']['status']}\n";
    } else {
        echo "   Ошибка: " . json_encode($userResponse['error'], JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";
    
    // Тест 6: Выполнение команды user.create с невалидными данными
    echo "6. Тестирование команды user.create (невалидные данные):\n";
    $invalidUserResponse = $client->sendRequest('user.create', [
        'name' => 'A', // Слишком короткое имя
        'email' => 'invalid-email', // Невалидный email
        'age' => 15 // Слишком молодой
    ]);
    if (isset($invalidUserResponse['error'])) {
        echo "   Ожидаемая ошибка валидации:\n";
        $errors = $invalidUserResponse['error']['data']['validation_errors'] ?? [];
        foreach ($errors as $error) {
            echo "   - {$error['property']}: {$error['message']}\n";
        }
    }
    echo "\n";
    
    // Тест 7: Попытка выполнения несуществующей команды
    echo "7. Тестирование несуществующей команды:\n";
    $unknownResponse = $client->sendRequest('unknown.command', ['test' => 'data']);
    if (isset($unknownResponse['error'])) {
        echo "   Ожидаемая ошибка: {$unknownResponse['error']['message']}\n";
    }
    echo "\n";
    
    // Тест 8: Попытка доступа без авторизации
    echo "8. Тестирование доступа без авторизации:\n";
    $clientWithoutAuth = new MCPClient(__DIR__ . '/mcp_server.php', 'wrong-key');
    $authErrorResponse = $clientWithoutAuth->sendRequest('echo', ['message' => 'test']);
    if (isset($authErrorResponse['error'])) {
        echo "   Ожидаемая ошибка авторизации: {$authErrorResponse['error']['message']}\n";
    }
    $clientWithoutAuth->close();
    echo "\n";
    
    echo "=== Все тесты завершены ===\n";
    
    // Закрываем соединение
    $client->close();
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    echo "Трассировка: " . $e->getTraceAsString() . "\n";
    exit(1);
}