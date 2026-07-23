<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use Amp\Future;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use quanzo\mcp\classes\validation\ValidationException;
use quanzo\mcp\commands\OllamaSearchCommand;
use quanzo\mcp\commands\RuWikiSearchCommand;
use quanzo\mcp\commands\UniSearchCommand;
use quanzo\mcp\commands\WikiSearchCommand;
use quanzo\mcp\wiki\dto\ArticleContentDto;
use quanzo\mcp\wiki\enums\ContentSourceType;
use quanzo\mcp\wiki\interfaces\ArticleSearcherInterface;

/**
 * Класс SearchCommandsTest
 *
 * Unit-тесты wiki/ollama search-команд с мок-searcher (без сети).
 */
class SearchCommandsTest extends TestCase
{
    /**
     * Создаёт мок-searcher с фиксированными статьями
     *
     * @param list<ArticleContentDto> $articles Статьи
     *
     * @return ArticleSearcherInterface
     */
    private function mockSearcher(array $articles): ArticleSearcherInterface
    {
        return new class ($articles) implements ArticleSearcherInterface {
            /**
             * @param list<ArticleContentDto> $articles
             */
            public function __construct(private array $articles)
            {
            }

            public function search(string $query, int $limit = 10, int $offset = 0): Future
            {
                return Future::complete($this->articles);
            }
        };
    }

    /**
     * Тест: WikiSearchCommand имя и схема
     *
     * @return void
     */
    public function testWikiSearchCommandNameAndSchema(): void
    {
        $cmd = new WikiSearchCommand();
        self::assertSame('wiki_search', $cmd->getName());
        self::assertArrayHasKey('search', $cmd->getInputSchema()['properties']);
    }

    /**
     * Тест: RuWikiSearchCommand имя
     *
     * @return void
     */
    public function testRuWikiSearchCommandName(): void
    {
        self::assertSame('ru_wiki_search', (new RuWikiSearchCommand())->getName());
    }

    /**
     * Тест: UniSearchCommand имя
     *
     * @return void
     */
    public function testUniSearchCommandName(): void
    {
        self::assertSame('uni_search', (new UniSearchCommand())->getName());
    }

    /**
     * Тест: OllamaSearchCommand имя
     *
     * @return void
     */
    public function testOllamaSearchCommandName(): void
    {
        self::assertSame('ollama_search', (new OllamaSearchCommand(''))->getName());
    }

    /**
     * Тест: успешный поиск с мок-searcher возвращает articles
     *
     * @return void
     */
    public function testExecuteWithMockSearcherReturnsArticles(): void
    {
        $article = new ArticleContentDto(
            'Content about PHP',
            'PHP',
            'https://en.wikipedia.org/wiki/PHP',
            ContentSourceType::WIKIPEDIA
        );
        $cmd = new UniSearchCommand('uni_search', 'test', [$this->mockSearcher([$article])]);
        $result = $cmd->execute(['search' => 'PHP']);

        self::assertArrayHasKey('articles', $result);
        self::assertCount(1, $result['articles']);
        self::assertSame('PHP', $result['articles'][0]['title']);
        self::assertNotSame('', $result['articles'][0]['content']);
    }

    /**
     * Тест: пустой search → ValidationException
     *
     * @return void
     */
    public function testEmptySearchFailsValidation(): void
    {
        $this->expectException(ValidationException::class);
        $cmd = new UniSearchCommand('uni_search', 'test', [$this->mockSearcher([])]);
        $cmd->execute(['search' => '']);
    }

    /**
     * Тест: отсутствие search → ValidationException
     *
     * @return void
     */
    public function testMissingSearchFailsValidation(): void
    {
        $this->expectException(ValidationException::class);
        $cmd = new UniSearchCommand('uni_search', 'test', [$this->mockSearcher([])]);
        $cmd->execute([]);
    }

    /**
     * Тест: лишнее свойство → ValidationException
     *
     * @return void
     */
    public function testAdditionalPropertyFails(): void
    {
        $this->expectException(ValidationException::class);
        $cmd = new UniSearchCommand('uni_search', 'test', [$this->mockSearcher([])]);
        $cmd->execute(['search' => 'ok', 'extra' => 1]);
    }

    /**
     * Data-driven: граничные и неверные аргументы
     *
     * @return array<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function searchArgsProvider(): array
    {
        return [
            'valid_short' => [['search' => 'a'], true],
            'valid_php' => [['search' => 'PHP'], true],
            'valid_cyrillic' => [['search' => 'Москва'], true],
            'valid_long' => [['search' => str_repeat('q', 200)], true],
            'empty_string' => [['search' => ''], false],
            'missing' => [[], false],
            'null_like_wrong_type' => [['search' => 123], false],
            'bool_search' => [['search' => true], false],
            'array_search' => [['search' => ['x']], false],
            'extra_field' => [['search' => 'ok', 'limit' => 1], false],
            'whitespace_only_ok_min1' => [['search' => ' '], true],
        ];
    }

    /**
     * Тест: data-driven валидация аргументов search
     *
     * @param array<string, mixed> $args Аргументы
     * @param bool $expectOk Ожидается успех валидации
     *
     * @return void
     */
    #[DataProvider('searchArgsProvider')]
    public function testSearchArgsValidation(array $args, bool $expectOk): void
    {
        $cmd = new UniSearchCommand('uni_search', 'test', [$this->mockSearcher([])]);
        if ($expectOk) {
            $result = $cmd->execute($args);
            self::assertArrayHasKey('articles', $result);
        } else {
            $this->expectException(ValidationException::class);
            $cmd->execute($args);
        }
    }

    /**
     * Тест: несколько searchers объединяют результаты
     *
     * @return void
     */
    public function testMultipleSearchersMergeResults(): void
    {
        $a1 = new ArticleContentDto('c1', 'T1', 'https://a', ContentSourceType::WIKIPEDIA);
        $a2 = new ArticleContentDto('c2', 'T2', 'https://b', ContentSourceType::RUWIKI);
        $cmd = new UniSearchCommand('uni_search', 'test', [
            $this->mockSearcher([$a1]),
            $this->mockSearcher([$a2]),
        ]);
        $result = $cmd->execute(['search' => 'test']);
        self::assertCount(2, $result['articles']);
    }
}
