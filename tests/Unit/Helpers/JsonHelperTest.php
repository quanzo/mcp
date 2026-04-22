<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use quanzo\mcp\helpers\JsonHelper;

class JsonHelperTest extends TestCase
{
    public function testEncodeDecodeRoundtrip(): void
    {
        $data = [
            'a' => 1,
            'b' => 'строка',
            'c' => ['x' => true],
        ];

        $json = JsonHelper::encode($data, JsonHelper::DEFAULT_COMPACT_FLAGS);
        self::assertIsString($json);

        /** @var array $decoded */
        $decoded = JsonHelper::decode($json, true);
        self::assertSame($data, $decoded);
    }

    public function testDecodeThrowsOnInvalidJson(): void
    {
        $this->expectException(\JsonException::class);
        JsonHelper::decode('{"broken": ', true);
    }
}
