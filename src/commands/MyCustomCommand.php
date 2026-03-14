<?php

namespace app\modules\neuron\mcp\commands;

/**
 * Класс MyCustomCommand
 *
 * Пример пользовательской команды с одним строковым параметром.
 */
class MyCustomCommand extends BaseCommand
{
    public function __construct()
    {
        $this->name = 'my.command';
        $this->description = 'Моя пользовательская команда';
    }

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

    protected function doExecute(array $params): array
    {
        // Логика команды
        return [
            'result' => 'Команда выполнена',
            'param' => $params['param1']
        ];
    }
}
