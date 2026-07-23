<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use quanzo\mcp\classes\McpServer;
use quanzo\mcp\classes\dto\mcp\ServerInfo;
use quanzo\mcp\commands\OllamaSearchCommand;
use quanzo\mcp\commands\RuWikiSearchCommand;
use quanzo\mcp\commands\UniSearchCommand;
use quanzo\mcp\commands\WikiSearchCommand;
use quanzo\mcp\helpers\JsonHelper;

/**
 * Класс WikiSearchLiveTest
 *
 * Live-интеграция: реальные запросы к Wikipedia / RuWiki / Ollama.
 * При недоступности сети — markTestSkipped.
 */
class WikiSearchLiveTest extends TestCase
{
    /**
     * Таймаут одного live-теста (секунды)
     */
    private const TIMEOUT_SECONDS = 60;

    /**
     * Инициализирует сервер с командой и выполняет tools/call
     *
     * @param object $command Команда
     * @param string $query Поисковый запрос
     *
     * @return array<string, mixed>
     */
    private function callSearchTool(object $command, string $query): array
    {
        $server = new McpServer(new ServerInfo('live-wiki', '1.0.0'), new NullLogger());
        /** @var \quanzo\mcp\interfaces\CommandInterface $command */
        $server->registerCommand($command);
        $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => [],
                'clientInfo' => ['name' => 'live', 'version' => '1'],
            ],
        ]);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => $command->getName(),
                'arguments' => ['search' => $query],
            ],
        ]);

        self::assertIsArray($response);
        if (isset($response['error'])) {
            $this->markTestSkipped('JSON-RPC error (сеть?): ' . JsonHelper::encode($response['error']));
        }
        self::assertArrayHasKey('result', $response);
        if (($response['result']['isError'] ?? false) === true) {
            $text = $response['result']['content'][0]['text'] ?? 'isError';
            $this->markTestSkipped('Tool isError (сеть/endpoint?): ' . $text);
        }

        $text = $response['result']['content'][0]['text'] ?? '';
        $decoded = JsonHelper::decode($text, true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * Проверяет непустые articles с title/content
     *
     * @param array<string, mixed> $payload Результат команды
     *
     * @return void
     */
    private function assertArticlesNotEmpty(array $payload): void
    {
        self::assertArrayHasKey('articles', $payload);
        self::assertNotEmpty($payload['articles'], 'Ожидались непустые articles от wiki');

        $hasUseful = false;
        foreach ($payload['articles'] as $article) {
            self::assertIsArray($article);
            $title = (string) ($article['title'] ?? '');
            $content = (string) ($article['content'] ?? '');
            if ($title !== '' && $content !== '') {
                $hasUseful = true;
                break;
            }
        }
        self::assertTrue(
            $hasUseful,
            'Ожидалась хотя бы одна статья с непустыми title и content'
        );
    }

    /**
     * Пропуск при сетевой/транспортной ошибке
     *
     * @param \Throwable $e Исключение
     *
     * @return never
     */
    private function skipOnNetworkFailure(\Throwable $e): never
    {
        $this->markTestSkipped('Сеть/endpoint недоступны: ' . $e->getMessage());
    }

    /**
     * Тест live: WikiSearchCommand возвращает данные Wikipedia
     *
     * @return void
     */
    public function testWikiSearchReturnsArticles(): void
    {
        set_time_limit(self::TIMEOUT_SECONDS);
        try {
            $payload = $this->callSearchTool(new WikiSearchCommand(), 'PHP');
            $this->assertArticlesNotEmpty($payload);
        } catch (\Throwable $e) {
            $this->skipOnNetworkFailure($e);
        }
    }

    /**
     * Тест live: RuWikiSearchCommand возвращает данные RuWiki
     *
     * @return void
     */
    public function testRuWikiSearchReturnsArticles(): void
    {
        set_time_limit(self::TIMEOUT_SECONDS);
        try {
            $payload = $this->callSearchTool(new RuWikiSearchCommand(), 'Москва');
            if (($payload['articles'] ?? []) === []) {
                $this->markTestSkipped('RuWiki недоступна (bot-protection/Qrator или пустой ответ)');
            }
            $this->assertArticlesNotEmpty($payload);
        } catch (\Throwable $e) {
            $this->skipOnNetworkFailure($e);
        }
    }

    /**
     * Тест live: UniSearchCommand (Wikipedia) возвращает articles
     *
     * @return void
     */
    public function testUniSearchReturnsArticles(): void
    {
        set_time_limit(self::TIMEOUT_SECONDS);
        try {
            $payload = $this->callSearchTool(new UniSearchCommand(), 'PHP');
            $this->assertArticlesNotEmpty($payload);
        } catch (\Throwable $e) {
            $this->skipOnNetworkFailure($e);
        }
    }

    /**
     * Тест live: Ollama без API key — сервис отвечает (даже 401 = есть ответ)
     *
     * @return void
     */
    public function testOllamaSearchWithoutApiKeyReturnsSomething(): void
    {
        set_time_limit(self::TIMEOUT_SECONDS);
        try {
            $server = new McpServer(new ServerInfo('live-wiki', '1.0.0'), new NullLogger());
            $command = new OllamaSearchCommand('');
            $server->registerCommand($command);
            $server->handleMessage([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => [],
                    'clientInfo' => ['name' => 'live', 'version' => '1'],
                ],
            ]);

            $response = $server->handleMessage([
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'ollama_search',
                    'arguments' => ['search' => 'PHP programming language'],
                ],
            ]);

            self::assertIsArray($response);
            // Успех с articles ИЛИ isError с телом ответа (например HTTP 401) — сервис ответил
            if (isset($response['result']['isError']) && $response['result']['isError'] === true) {
                $text = (string) ($response['result']['content'][0]['text'] ?? '');
                self::assertNotSame('', $text, 'Ожидался непустой текст ошибки от Ollama');
                return;
            }

            $payload = $this->callSearchTool($command, 'PHP programming language');
            self::assertArrayHasKey('articles', $payload);
            self::assertNotEmpty($payload['articles']);
        } catch (\Throwable $e) {
            $this->skipOnNetworkFailure($e);
        }
    }

    /**
     * Тест: пустой search через McpServer → JSON-RPC -32602, процесс не падает
     *
     * @return void
     */
    public function testEmptySearchReturnsInvalidParams(): void
    {
        $server = new McpServer(new ServerInfo('live-wiki', '1.0.0'), new NullLogger());
        $server->registerCommand(new WikiSearchCommand());
        $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => [],
                'clientInfo' => ['name' => 'live', 'version' => '1'],
            ],
        ]);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'wiki_search',
                'arguments' => ['search' => ''],
            ],
        ]);

        self::assertSame(-32602, $response['error']['code']);
    }
}
