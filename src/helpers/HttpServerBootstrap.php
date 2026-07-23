<?php

declare(strict_types=1);

namespace quanzo\mcp\helpers;

use Psr\Log\LoggerInterface;
use quanzo\mcp\classes\McpServerFactory;
use quanzo\mcp\classes\log\FileLogger;
use quanzo\mcp\classes\transport\StreamableHttpTransport;

/**
 * Класс HttpServerBootstrap
 *
 * Общая подготовка Streamable HTTP entry points: каталоги, Bearer, Origin, transport.
 *
 * Пример использования:
 *   $transport = HttpServerBootstrap::createTransport($projectRoot, $logger);
 */
final class HttpServerBootstrap
{
    /**
     * Создаёт необходимые каталоги проекта
     *
     * @param string $projectRoot Корень проекта
     *
     * @return void
     */
    public static function ensureDirectories(string $projectRoot): void
    {
        foreach (['logs', 'config', 'data'] as $dir) {
            $path = $projectRoot . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    /**
     * Читает Bearer из MCP_HTTP_BEARER
     *
     * @return string|null
     */
    public static function resolveBearer(): ?string
    {
        $bearer = getenv('MCP_HTTP_BEARER') ?: null;
        if ($bearer === '') {
            return null;
        }

        return $bearer;
    }

    /**
     * Читает список Origin из MCP_ALLOWED_ORIGINS (через запятую)
     *
     * @return list<string>
     */
    public static function resolveAllowedOrigins(): array
    {
        $originsEnv = getenv('MCP_ALLOWED_ORIGINS') ?: '';
        if ($originsEnv === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $originsEnv))));
    }

    /**
     * Создаёт FileLogger для HTTP-сервера
     *
     * @param string $projectRoot Корень проекта
     * @param string $logFile Имя файла в logs/
     *
     * @return FileLogger
     */
    public static function createFileLogger(string $projectRoot, string $logFile): FileLogger
    {
        return new FileLogger(
            $projectRoot . '/logs/' . $logFile,
            \Psr\Log\LogLevel::INFO
        );
    }

    /**
     * Собирает StreamableHttpTransport с демо-сервером
     *
     * @param string $projectRoot Корень проекта
     * @param LoggerInterface $logger Логгер
     *
     * @return StreamableHttpTransport
     */
    public static function createTransport(string $projectRoot, LoggerInterface $logger): StreamableHttpTransport
    {
        self::ensureDirectories($projectRoot);
        $server = McpServerFactory::createDefault($projectRoot, $logger);

        return new StreamableHttpTransport(
            $server,
            self::resolveBearer(),
            self::resolveAllowedOrigins()
        );
    }
}
