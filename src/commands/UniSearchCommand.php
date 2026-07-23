<?php

declare(strict_types=1);

namespace quanzo\mcp\commands;

use quanzo\mcp\wiki\dto\SearchToolResultDto;
use quanzo\mcp\wiki\interfaces\ArticleSearcherInterface;
use quanzo\mcp\wiki\search\ArticleSearchManager;
use quanzo\mcp\wiki\search\WikipediaArticleSearcher;

/**
 * Класс UniSearchCommand
 *
 * Универсальный MCP tool поиска статей через набор ArticleSearcherInterface.
 * По умолчанию — Wikipedia ru + en.
 *
 * Пример использования:
 *   $server->registerCommand(new UniSearchCommand());
 *   // tools/call { "name": "uni_search", "arguments": { "search": "PHP" } }
 */
class UniSearchCommand extends BaseCommand
{
    /**
     * Поисковики статей
     *
     * @var list<ArticleSearcherInterface>
     */
    protected array $searchers;

    /**
     * Лимит результатов на один источник
     *
     * @var int
     */
    protected int $defaultLimitPerSource = 2;

    /**
     * Конструктор UniSearchCommand
     *
     * @param string $name Имя tool
     * @param string $description Описание для LLM
     * @param list<ArticleSearcherInterface> $searchers Поисковики (пусто = Wikipedia ru/en)
     */
    public function __construct(
        string $name = 'uni_search',
        string $description = 'Выполняет поиск терминов и определений. Поддерживает загрузку страниц.',
        array $searchers = []
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->searchers = $searchers !== []
            ? array_values($searchers)
            : [
                new WikipediaArticleSearcher('ru'),
                new WikipediaArticleSearcher('en'),
            ];
    }

    /**
     * Схема аргументов: обязательный search
     *
     * @return array<string, mixed>
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'description' => 'Поисковый запрос',
                ],
            ],
            'required' => ['search'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Выполняет поиск и возвращает articles
     *
     * @param array<string, mixed> $params Параметры (search)
     *
     * @return array<string, mixed>
     */
    protected function doExecute(array $params): array
    {
        $query = (string) $params['search'];
        $manager = new ArticleSearchManager($this->searchers);
        $articles = $manager->searchAll($query, $this->defaultLimitPerSource)->await();
        // Отбрасываем пустые загрузки (частичный сбой loader / rate limit)
        $articles = array_values(array_filter(
            $articles,
            static fn ($a) => $a instanceof \quanzo\mcp\wiki\dto\ArticleContentDto
                && $a->title !== ''
                && $a->content !== ''
        ));

        return (new SearchToolResultDto($articles))->toArray();
    }
}
