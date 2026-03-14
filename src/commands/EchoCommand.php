<?php

namespace app\modules\neuron\mcp\commands;

/**
 * Класс EchoCommand
 *
 * Пример простой команды, которая возвращает переданное сообщение.
 * Демонстрирует базовое использование CommandInterface.
 */
class EchoCommand extends BaseCommand
{
    /**
     * Конструктор EchoCommand
     */
    public function __construct()
    {
        $this->name = 'echo';
        $this->description = 'Возвращает переданный текст';
    }

    /**
     * Возвращает схему входных параметров для команды echo
     *
     * @return array Схема в формате JSON Schema
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'Текст для эхо'
                ]
            ],
            'required' => ['message']
        ];
    }

    /**
     * Выполняет логику команды echo
     *
     * @param array $params Входные параметры (уже прошедшие валидацию)
     *
     * @return array Результат выполнения команды
     */
    protected function doExecute(array $params): array
    {
        return [
            'result' => $params['message'],
            'timestamp' => date('c'),
            'length' => strlen($params['message'])
        ];
    }
}
