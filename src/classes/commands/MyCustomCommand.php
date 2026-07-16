<?php

namespace quanzo\mcp\classes\commands;

/**
 * Класс MyCustomCommand
 *
 * Пример пользовательской команды с одним строковым параметром.
 */
class MyCustomCommand extends BaseCommand
{
    /**
     * Конструктор MyCustomCommand
     */
    public function __construct()
    {
        $this->name = 'my_command';
        $this->description = 'Моя пользовательская команда';
    }

    /**
     * Возвращает схему входных параметров
     *
     * @return array<string, mixed>
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'param1' => [
                    'type' => 'string',
                    'description' => 'Параметр 1'
                ]
            ],
            'required' => ['param1']
        ];
    }

    /**
     * Выполняет логику команды
     *
     * @param array<string, mixed> $params Входные параметры
     *
     * @return array<string, mixed>
     */
    protected function doExecute(array $params): array
    {
        return [
            'result' => 'Команда выполнена',
            'param' => $params['param1']
        ];
    }
}
