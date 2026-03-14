<?php

namespace app\modules\neuron\mcp\resources;

use app\modules\neuron\mcp\interfaces\ResourceInterface;

class MyResource implements ResourceInterface
{
    public function getUri(): string
    {
        return 'myresource://data';
    }

    public function getMimeType(): string
    {
        return 'application/json';
    }

    public function getContent(): string
    {
        return json_encode(['data' => 'Пример данных']);
    }

    public function getMetadata(): array
    {
        return ['type' => 'custom'];
    }

    public function matchesUri(string $uri): bool
    {
        return $uri === $this->getUri();
    }
}
