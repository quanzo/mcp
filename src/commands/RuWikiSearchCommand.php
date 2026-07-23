<?php

declare(strict_types=1);

namespace quanzo\mcp\commands;

use quanzo\mcp\wiki\loader\RuWikiFullLoader;
use quanzo\mcp\wiki\search\RuWikiArticleSearcher;

/**
 * Класс RuWikiSearchCommand
 *
 * Поиск в российской RuWiki через RuWikiArticleSearcher + RuWikiFullLoader.
 *
 * Пример использования:
 *   $server->registerCommand(new RuWikiSearchCommand());
 */
class RuWikiSearchCommand extends UniSearchCommand
{
    /**
     * Конструктор RuWikiSearchCommand
     */
    public function __construct()
    {
        parent::__construct(
            'ru_wiki_search',
            'Выполняет поиск терминов, определений и другой информации в российской RuWiki.',
            [new RuWikiArticleSearcher(new RuWikiFullLoader())]
        );
    }
}
