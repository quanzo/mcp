<?php

namespace app\modules\neuron\mcp\client;

/**
 * Класс MCPClient
 * 
 * Обеспечивает взаимодействие с MCP сервером через stdio.
 * Позволяет отправлять запросы и получать ответы от сервера.
 */
class MCPClient
{
    /**
     * Дочерний процесс сервера
     * @var resource
     */
    private $process;
    
    /**
     * Каналы общения с дочерним процессом
     * @var array
     */
    private $pipes;
    
    /**
     * Ключ авторизации
     * @var string
     */
    private $authKey;
    
    /**
     * Счетчик идентификаторов запросов
     * @var int
     */
    private $requestId = 1;
    
    /**
     * Конструктор MCPClient
     * 
     * @param string $serverScript Путь к скрипту сервера
     * @param string $authKey Ключ авторизации
     * 
     * @throws \RuntimeException Если не удалось запустить MCP сервер
     */
    public function __construct(string $serverScript, string $authKey = 'default-secret-key-123')
    {
        $this->authKey = $authKey;
        
        // Команда для запуска сервера
        $command = "php " . escapeshellarg($serverScript);
        
        // Дескрипторы для общения с дочерним процессом
        $descriptors = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"]   // stderr
        ];
        
        // Запускаем сервер как дочерний процесс
        $this->process = proc_open($command, $descriptors, $this->pipes);
        
        if (!is_resource($this->process)) {
            throw new \RuntimeException("Не удалось запустить MCP сервер");
        }
        
        // Устанавливаем неблокирующий режим для чтения
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);
        
        echo "MCP сервер запущен (PID: " . proc_get_status($this->process)['pid'] . ")\n";
    }
    
    /**
     * Отправляет запрос на сервер
     * 
     * @param string $method Метод/команда
     * @param array $params Параметры запроса
     * @param int|null $id Идентификатор запроса
     * 
     * @return array Ответ сервера
     * 
     * @throws \RuntimeException Если произошел таймаут или некорректный ответ
     */
    public function sendRequest(string $method, array $params = [], ?int $id = null): array
    {
        $id = $id ?? $this->requestId++;
        
        // Добавляем ключ авторизации к параметрам
        $params['auth'] = $this->authKey;
        
        $request = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => $params
        ];
        
        $jsonRequest = json_encode($request) . "\n";
        
        // Записываем запрос в stdin сервера
        fwrite($this->pipes[0], $jsonRequest);
        fflush($this->pipes[0]);
        
        echo "Отправлен запрос #$id: $method\n";
        
        // Читаем ответ из stdout сервера
        $response = '';
        $startTime = microtime(true);
        $timeout = 5; // секунд
        
        while (true) {
            $line = fgets($this->pipes[1]);
            
            if ($line !== false) {
                $response .= $line;
                if (strpos($line, "\n") !== false) {
                    break;
                }
            }
            
            // Проверка таймаута
            if (microtime(true) - $startTime > $timeout) {
                throw new \RuntimeException("Таймаут ожидания ответа от сервера");
            }
            
            usleep(10000); // 10ms пауза
        }
        
        // Читаем ошибки из stderr
        $errors = stream_get_contents($this->pipes[2]);
        if (!empty($errors)) {
            echo "Stderr сервера: $errors\n";
        }
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Некорректный JSON ответ: " . json_last_error_msg());
        }
        
        return $decodedResponse;
    }
    
    /**
     * Получает список доступных команд
     * 
     * @return array Список команд
     * 
     * @throws \RuntimeException Если произошла ошибка при получении списка команд
     */
    public function listCommands(): array
    {
        $response = $this->sendRequest('mcp.listCommands', []);
        
        if (isset($response['error'])) {
            throw new \RuntimeException("Ошибка при получении списка команд: " . 
                json_encode($response['error']));
        }
        
        return $response['result']['commands'] ?? [];
    }
    
    /**
     * Получает список доступных ресурсов
     * 
     * @return array Список ресурсов
     * 
     * @throws \RuntimeException Если произошла ошибка при получении списка ресурсов
     */
    public function listResources(): array
    {
        $response = $this->sendRequest('mcp.listResources', []);
        
        if (isset($response['error'])) {
            throw new \RuntimeException("Ошибка при получении списка ресурсов: " . 
                json_encode($response['error']));
        }
        
        return $response['result']['resources'] ?? [];
    }
    
    /**
     * Читает содержимое ресурса
     * 
     * @param string $uri URI ресурса
     * 
     * @return string Содержимое ресурса
     * 
     * @throws \RuntimeException Если произошла ошибка при чтении ресурса
     */
    public function readResource(string $uri): string
    {
        $response = $this->sendRequest('mcp.readResource', ['uri' => $uri]);
        
        if (isset($response['error'])) {
            throw new \RuntimeException("Ошибка при чтении ресурса: " . 
                json_encode($response['error']));
        }
        
        return $response['result']['content'] ?? '';
    }
    
    /**
     * Закрывает соединение с сервером
     */
    public function close(): void
    {
        if (is_resource($this->process)) {
            // Закрываем каналы
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            
            // Завершаем процесс
            proc_terminate($this->process);
            proc_close($this->process);
        }
        
        echo "Соединение с MCP сервером закрыто\n";
    }
    
    /**
     * Деструктор
     */
    public function __destruct()
    {
        $this->close();
    }
}