<?php

declare(strict_types=1);

namespace quanzo\mcp\classes\dto\mcp;

/**
 * DTO ResourceDefinition
 *
 * Описание ресурса для ответа resources/list.
 *
 * Пример использования:
 *   $res = new ResourceDefinition('file://logs/a.log', 'Server log', 'text/plain', 'Log file');
 */
class ResourceDefinition
{
    /**
     * URI ресурса
     *
     * @var string
     */
    private string $uri;

    /**
     * Человекочитаемое имя
     *
     * @var string
     */
    private string $name;

    /**
     * MIME-тип содержимого
     *
     * @var string|null
     */
    private ?string $mimeType;

    /**
     * Описание ресурса
     *
     * @var string|null
     */
    private ?string $description;

    /**
     * Конструктор ResourceDefinition
     *
     * @param string $uri URI ресурса
     * @param string $name Имя ресурса
     * @param string|null $mimeType MIME-тип
     * @param string|null $description Описание
     */
    public function __construct(
        string $uri,
        string $name,
        ?string $mimeType = null,
        ?string $description = null
    ) {
        $this->uri = $uri;
        $this->name = $name;
        $this->mimeType = $mimeType;
        $this->description = $description;
    }

    /**
     * Сериализует определение ресурса в массив
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'uri' => $this->uri,
            'name' => $this->name,
        ];

        if ($this->description !== null) {
            $out['description'] = $this->description;
        }

        if ($this->mimeType !== null) {
            $out['mimeType'] = $this->mimeType;
        }

        return $out;
    }
}
