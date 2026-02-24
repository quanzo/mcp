<?php
/**
 * Класс CalculateCommand
 * 
 * Пример команды для выполнения математических операций.
 * Демонстрирует валидацию входных параметров и обработку ошибок.
 */
namespace app\modules\neuron\mcp\commands;

class CalculateCommand extends BaseCommand
{
    /**
     * Конструктор CalculateCommand
     */
    public function __construct()
    {
        $this->name = 'calculate';
        $this->description = 'Выполняет математические операции';
    }
    
    /**
     * Возвращает схему входных параметров для команды calculate
     * 
     * @return array Схема в формате JSON Schema
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'enum' => ['add', 'subtract', 'multiply', 'divide'],
                    'description' => 'Тип операции'
                ],
                'a' => [
                    'type' => 'number',
                    'description' => 'Первое число'
                ],
                'b' => [
                    'type' => 'number',
                    'description' => 'Второе число'
                ]
            ],
            'required' => ['operation', 'a', 'b']
        ];
    }
    
    /**
     * Выполняет математическую операцию
     * 
     * @param array $params Входные параметры (уже прошедшие валидацию)
     * 
     * @return array Результат вычисления
     * 
     * @throws \InvalidArgumentException Если операция деления на ноль
     */
    protected function doExecute(array $params): array
    {
        $a = $params['a'];
        $b = $params['b'];
        $operation = $params['operation'];
        
        switch ($operation) {
            case 'add':
                $result = $a + $b;
                break;
            case 'subtract':
                $result = $a - $b;
                break;
            case 'multiply':
                $result = $a * $b;
                break;
            case 'divide':
                if ($b == 0) {
                    throw new \InvalidArgumentException('Деление на ноль');
                }
                $result = $a / $b;
                break;
            default:
                throw new \InvalidArgumentException('Неизвестная операция: ' . $operation);
        }
        
        return [
            'result' => $result,
            'operation' => $operation,
            'expression' => "$a $operation $b",
            'timestamp' => date('c')
        ];
    }
}