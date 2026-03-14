<?php

namespace app\modules\neuron\mcp\commands;

use app\modules\neuron\mcp\commands\BaseCommand;
use app\modules\neuron\mcp\commands\ValidationException;

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
