<?php

declare(strict_types=1);

namespace quanzo\mcp\wiki\ollama;

use Amp\Future;
use quanzo\mcp\wiki\dto\ArticleContentDto;
use quanzo\mcp\wiki\enums\ContentSourceType;
use quanzo\mcp\wiki\search\ArticleSearcherAbstract;

/**
 * Класс OllamaArticleSearcher
 *
 * Поиск через Ollama Web Search API. Результаты уже содержат content — отдельный fetch не нужен.
 *
 * Пример использования:
 *   $searcher = new OllamaArticleSearcher(new OllamaApiService());
 *   $articles = $searcher->search('PHP')->await();
 */
class OllamaArticleSearcher extends ArticleSearcherAbstract
{
    /**
     * Сервис Ollama API
     *
     * @var OllamaApiService
     */
    private OllamaApiService $ollamaService;

    /**
     * Тип источника
     *
     * @var ContentSourceType
     */
    private ContentSourceType $sourceType;

    /**
     * Конструктор OllamaArticleSearcher
     *
     * @param OllamaApiService|null $ollamaService Сервис API (по умолчанию без ключа)
     */
    public function __construct(?OllamaApiService $ollamaService = null)
    {
        parent::__construct();
        $this->ollamaService = $ollamaService ?? new OllamaApiService();
        $this->sourceType = ContentSourceType::GENERIC;
    }

    /**
     * Выполняет поиск через Ollama Web Search
     *
     * @param string $query Поисковый запрос
     * @param int $limit Лимит
     * @param int $offset Смещение (игнорируется API)
     *
     * @return Future<list<ArticleContentDto>>
     */
    public function search(string $query, int $limit = 10, int $offset = 0): Future
    {
        return \Amp\async(function () use ($query, $limit) {
            $searchResults = $this->ollamaService->webSearch($query)->await();

            if ($limit > 0 && count($searchResults) > $limit) {
                $searchResults = array_slice($searchResults, 0, $limit);
            }

            $articles = [];
            foreach ($searchResults as $result) {
                if (!is_array($result)) {
                    continue;
                }
                $articles[] = $this->createArticleDto($result);
            }

            return $articles;
        });
    }

    /**
     * Загрузка по URL не используется — контент приходит из webSearch
     *
     * @param list<string> $urls URL
     *
     * @return Future<list<ArticleContentDto>>
     */
    protected function loadArticlesContent(array $urls): Future
    {
        return \Amp\async(static fn () => []);
    }

    /**
     * Создаёт DTO из элемента ответа Ollama
     *
     * @param array<string, mixed> $articleData Элемент results
     *
     * @return ArticleContentDto
     */
    protected function createArticleDto(array $articleData): ArticleContentDto
    {
        return new ArticleContentDto(
            content: (string) ($articleData['content'] ?? ''),
            title: (string) ($articleData['title'] ?? ''),
            sourceUrl: (string) ($articleData['url'] ?? ''),
            sourceType: $this->sourceType,
            metadata: [
                'search_source' => 'ollama_web_search',
            ]
        );
    }

    /**
     * Возвращает тип источника
     *
     * @return ContentSourceType
     */
    protected function getSourceType(): ContentSourceType
    {
        return $this->sourceType;
    }
}
