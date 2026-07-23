<?php

declare(strict_types=1);

namespace quanzo\mcp\commands;

use quanzo\mcp\wiki\ollama\OllamaApiService;
use quanzo\mcp\wiki\ollama\OllamaArticleSearcher;

/**
 * Класс OllamaSearchCommand
 *
 * Поиск в интернете через Ollama Web Search API. API key опционален (MCP_OLLAMA_API_KEY).
 *
 * Пример использования:
 *   $server->registerCommand(new OllamaSearchCommand());
 */
class OllamaSearchCommand extends UniSearchCommand
{
    /**
     * Конструктор OllamaSearchCommand
     *
     * @param string|null $ollamaApiKey API-ключ (null = из MCP_OLLAMA_API_KEY или пусто)
     */
    public function __construct(?string $ollamaApiKey = null)
    {
        $key = $ollamaApiKey;
        if ($key === null) {
            $env = getenv('MCP_OLLAMA_API_KEY');
            $key = is_string($env) ? $env : '';
        }

        $service = new OllamaApiService('https://ollama.com', $key);
        parent::__construct(
            'ollama_search',
            'Выполняет поиск в интернете терминов и определений через Ollama Web Search.',
            [new OllamaArticleSearcher($service)]
        );
    }
}
