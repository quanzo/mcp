<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use quanzo\mcp\helpers\TemplateRenderer;

class TemplateRendererTest extends TestCase
{
    public function testRenderRendersPhpTemplateWithData(): void
    {
        $templatesRoot = dirname(__DIR__, 3) . '/src/templates';
        $renderer = new TemplateRenderer($templatesRoot);

        $html = $renderer->renderFromRoot('http/api_root_amp.php', [
            'activeConnections' => 1,
            'currentYear' => 2026,
            'host' => '127.0.0.1',
            'memoryUsage' => '1 MB',
            'phpVersion' => '8.1.0',
            'port' => 8080,
            'uptime' => '1s',
        ]);

        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('127.0.0.1', $html);
        self::assertStringContainsString('8080', $html);
    }
}
