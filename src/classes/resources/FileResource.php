<?php

namespace quanzo\mcp\resources;

use quanzo\mcp\interfaces\ResourceInterface;

/**
 * Класс FileResource
 *
 * Реализация ResourceInterface для работы с файловыми ресурсами.
 * Поддерживает паттерны в URI для доступа к нескольким файлам.
 */
class FileResource implements ResourceInterface
{
    /**
     * Паттерн URI ресурса
     * @var string
     */
    private string $uriPattern;

    /**
     * MIME-тип содержимого
     * @var string
     */
    private string $mimeType;

    /**
     * Базовый путь к файлам
     * @var string
     */
    private string $basePath;

    /**
     * Конструктор FileResource
     *
     * @param string $uriPattern Паттерн URI (например, 'file://logs/*')
     * @param string $mimeType MIME-тип содержимого
     * @param string $basePath Базовый путь к файлам
     */
    public function __construct(string $uriPattern, string $mimeType, string $basePath = '')
    {
        $this->uriPattern = $uriPattern;
        $this->mimeType = $mimeType;
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Возвращает URI ресурса
     *
     * @return string URI ресурса
     */
    public function getUri(): string
    {
        return $this->uriPattern;
    }

    /**
     * Возвращает MIME-тип содержимого
     *
     * @return string MIME-тип
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Возвращает содержимое ресурса
     *
     * @param string|null $requestedUri Запрошенный URI; при паттерне — конкретный URI для выбора файла
     *
     * @return string Содержимое файла
     *
     * @throws \RuntimeException Если файл не найден или недоступен для чтения
     */
    public function getContent(?string $requestedUri = null): string
    {
        $uriForFile = $requestedUri !== null && $this->matchesUri($requestedUri)
            ? $requestedUri
            : $this->uriPattern;
        $filename = $this->extractFilenameFromUri($uriForFile);
        $filepath = $this->basePath . '/' . $filename;

        if (!file_exists($filepath)) {
            throw new \RuntimeException("File not found: $filepath");
        }

        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: $filepath");
        }

        return $content;
    }

    /**
     * Возвращает метаданные ресурса
     *
     * @return array Метаданные ресурса
     */
    public function getMetadata(): array
    {
        return [
            'type' => 'file',
            'pattern' => $this->uriPattern,
            'basePath' => $this->basePath,
            'timestamp' => time()
        ];
    }

    /**
     * Проверяет соответствие URI паттерну ресурса
     *
     * @param string $uri URI для проверки
     *
     * @return bool true если URI соответствует паттерну, false в противном случае
     */
    public function matchesUri(string $uri): bool
    {
        // Простая проверка по префиксу
        $pattern = str_replace('*', '.*', $this->uriPattern);
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\.\*', '.*', $pattern);

        return preg_match("#^{$pattern}$#", $uri) === 1;
    }

    /**
     * Извлекает имя файла из URI
     *
     * @param string $uri URI ресурса
     *
     * @return string Имя файла
     */
    private function extractFilenameFromUri(string $uri): string
    {
        // Извлекаем имя файла из URI
        $parts = explode('/', $uri);
        return end($parts);
    }
}
