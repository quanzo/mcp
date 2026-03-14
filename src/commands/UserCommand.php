<?php

namespace app\modules\neuron\mcp\commands;

use app\modules\neuron\mcp\validation\ValidationException;

/**
 * Класс UserCommand
 *
 * Пример команды для создания пользователя с полной валидацией входных параметров.
 * Демонстрирует использование JSON Schema для валидации и бизнес-логики.
 */
class UserCommand extends BaseCommand
{
    /**
     * Список занятых email или callable(string): bool для проверки занятости
     *
     * @var array<int, string>|callable(string): bool|null
     */
    private $emailTakenChecker;

    /**
     * Конструктор UserCommand
     *
     * @param array<int, string>|callable(string): bool|null $emailTakenChecker Список занятых email или callable
     */
    public function __construct($emailTakenChecker = null)
    {
        $this->name = 'user.create';
        $this->description = 'Создание нового пользователя';
        $this->emailTakenChecker = $emailTakenChecker ?? ['admin@example.com', 'test@example.com'];
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
        if (is_callable($this->emailTakenChecker)) {
            return ($this->emailTakenChecker)($email);
        }

        return in_array($email, $this->emailTakenChecker, true);
    }
}
