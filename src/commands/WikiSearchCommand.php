<?php

declare(strict_types=1);

namespace quanzo\mcp\commands;

/**
 * Класс WikiSearchCommand
 *
 * Поиск в Wikipedia (ru/en) — обёртка над UniSearchCommand с дефолтными searchers.
 *
 * Пример использования:
 *   $server->registerCommand(new WikiSearchCommand());
 */
class WikiSearchCommand extends UniSearchCommand
{
    /**
     * Конструктор WikiSearchCommand
     */
    public function __construct()
    {
        parent::__construct(
            'wiki_search',
            'Выполняет поиск терминов, определений и другой информации в Wikipedia (ru/en).'
        );
    }
}
