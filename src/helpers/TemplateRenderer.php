<?php

declare(strict_types=1);

namespace quanzo\mcp\helpers;

/**
 * TemplateRenderer
 *
 * Рендерит PHP-шаблоны из каталога `src/templates`.
 * Данные передаются массивом и доступны внутри шаблона как переменные.
 *
 * Пример использования:
 *   $html = (new TemplateRenderer($root))->renderFromRoot('http/api_root_amp.php', ['host' => '127.0.0.1']);
 */
final class TemplateRenderer
{
    /**
     * Абсолютный путь к корню шаблонов
     *
     * @var string
     */
    private string $templatesRoot;

    /**
     * @param string $templatesRoot Абсолютный путь к корню шаблонов (например `src/templates`).
     */
    public function __construct(string $templatesRoot)
    {
        $this->templatesRoot = rtrim($templatesRoot, '/');

        if (!is_dir($this->templatesRoot)) {
            throw new \RuntimeException('Templates directory not found: ' . $this->templatesRoot);
        }
    }

    /**
     * Рендерит PHP-шаблон и возвращает строку.
     *
     * @param string $templatePath Абсолютный путь к шаблону.
     * @param array<string, mixed> $data Данные для шаблона.
     */
    public function render(string $templatePath, array $data = []): string
    {
        if (!is_file($templatePath)) {
            throw new \RuntimeException('Template not found: ' . $templatePath);
        }

        ob_start();
        try {
            extract($data, EXTR_SKIP);
            /** @psalm-suppress UnresolvableInclude */
            include $templatePath;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Рендерит шаблон по относительному пути от корня шаблонов.
     *
     * @param string $relativePath Например `http/api_root_amp.php`.
     * @param array<string, mixed> $data
     */
    public function renderFromRoot(string $relativePath, array $data = []): string
    {
        $relativePath = ltrim($relativePath, '/');
        return $this->render($this->templatesRoot . '/' . $relativePath, $data);
    }
}
