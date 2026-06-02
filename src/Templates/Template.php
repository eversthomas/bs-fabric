<?php

declare(strict_types=1);

namespace BsFabric\Templates;

final class Template
{
    /**
     * @param array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     title_field: string|null,
     *     settings: array<string, mixed>,
     *     created_at: string
     * } $data
     */
    public function __construct(
        private readonly array $data,
    ) {
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'id' => $this->data['id'],
            'name' => $this->data['name'],
            'label' => $this->data['label'],
            'title_field' => $this->data['title_field'],
            'settings' => $this->data['settings'],
            'created_at' => $this->data['created_at'],
            default => null,
        };
    }

    public function getId(): int
    {
        return $this->data['id'];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     title_field: string|null,
     *     settings: array<string, mixed>,
     *     created_at: string
     * }
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
