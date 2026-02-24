<?php
/**
 * Класс UserCommand
 * 
 * Пример команды для создания пользователя с полной валидацией входных параметров.
 * Демонстрирует использование JSON Schema для валидации и бизнес-логики.
 */
namespace app\modules\neuron\mcp\commands;

class UserCommand extends BaseCommand
{
    /**
     * Конструктор UserCommand
     */
    public function __construct()
    {
        $this->name = 'user.create';
        $this->description = 'Создание нового пользователя';
    }
    
    /**
     * Возвращает схему входных параметров для создания пользователя
     * 
     * @return array Схема в формате JSON Schema
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'minLength' => 2,
                    'maxLength' => 50,
                    'description' => 'Имя пользователя'
                ],
                'email' => [
                    'type' => 'string',
                    'pattern' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$',
                    'description' => 'Email пользователя'
                ],
                'age' => [
                    'type' => 'integer',
                    'minimum' => 18,
                    'maximum' => 120,
                    'description' => 'Возраст пользователя'
                ],
                'roles' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => ['user', 'admin', 'moderator']
                    ],
                    'minItems' => 1,
                    'description' => 'Роли пользователя'
                ]
            ],
            'required' => ['name', 'email'],
            'additionalProperties' => false
        ];
    }
    
    /**
     * Выполняет логику создания пользователя
     * 
     * @param array $params Входные параметры (уже прошедшие валидации)
     * 
     * @return array Результат создания пользователя
     * 
     * @throws ValidationException Если email уже используется
     */
    protected function doExecute(array $params): array
    {
        // Дополнительная бизнес-логика валидации
        if (isset($params['email']) && $this->isEmailTaken($params['email'])) {
            throw new ValidationException([
                [
                    'property' => 'email',
                    'message' => 'Email уже используется'
                ]
            ]);
        }
        
        // Имитация создания пользователя в базе данных
        $userId = uniqid('user_', true);
        
        return [
            'id' => $userId,
            'name' => $params['name'],
            'email' => $params['email'],
            'age' => $params['age'] ?? null,
            'roles' => $params['roles'] ?? ['user'],
            'created_at' => date('c'),
            'status' => 'active',
            'metadata' => [
                'version' => '1.0',
                'source' => 'mcp-server'
            ]
        ];
    }
    
    /**
     * Проверяет, занят ли email (имитация проверки в базе данных)
     * 
     * @param string $email Email для проверки
     * 
     * @return bool true если email занят, false если доступен
     */
    private function isEmailTaken(string $email): bool
    {
        // Имитация проверки существующего email в базе данных
        $takenEmails = ['admin@example.com', 'test@example.com'];
        return in_array($email, $takenEmails);
    }
}