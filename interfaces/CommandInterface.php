<?php
/**
 * Интерфейс CommandInterface
 * 
 * Определяет контракт для команд MCP сервера.
 * Каждая команда представляет собой отдельную операцию, которая может быть
 * выполнена сервером по запросу клиента.
 */
namespace app\modules\neuron\mcp\interfaces;

interface CommandInterface
{
    /**
     * Возвращает имя команды для идентификации в MCP протоколе
     * 
     * @return string Уникальное имя команды
     */
    public function getName(): string;
    
    /**
     * Возвращает описание команды для представления LLM
     * 
     * @return string Человекочитаемое описание команды
     */
    public function getDescription(): string;
    
    /**
     * Возвращает схему входных параметров в формате JSON Schema
     * 
     * @return array Массив, описывающий схему входных параметров
     */
    public function getInputSchema(): array;
    
    /**
     * Валидирует входные параметры команды
     * 
     * @param array $params Входные параметры для валидации
     * 
     * @return void
     * 
     * @throws \app\modules\neuron\mcp\commands\ValidationException Если параметры не соответствуют схеме
     */
    public function validateInput(array $params): void;
    
    /**
     * Выполняет команду с переданными параметрами
     * 
     * @param array $params Входные параметры команды
     * 
     * @return array Результат выполнения команды
     * 
     * @throws \Exception Если произошла ошибка при выполнении команды
     */
    public function execute(array $params): array;
}
