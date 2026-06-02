<?php

declare(strict_types=1);

namespace BsFabric\Entries;

use BsFabric\Fields\FieldRegistry;
use BsFabric\Relations\RelationRepository;

final class Entry
{
    /**
     * @param array{
     *     id: int,
     *     template_id: int,
     *     title: string|null,
     *     status: string,
     *     language: string,
     *     created_by: int|null,
     *     created_at: string,
     *     updated_at: string
     * } $meta
     * @param array<string, array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>
     * }> $fieldsByName
     * @param array<string, mixed> $valuesByName
     */
    public function __construct(
        private readonly array $meta,
        private readonly array $fieldsByName,
        private readonly array $valuesByName,
        private readonly FieldRegistry $fieldRegistry,
        private readonly RelationRepository $relationRepository,
        private readonly ?\Closure $entryLoader,
    ) {
    }

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->meta)) {
            return $this->meta[$name];
        }

        if (! isset($this->fieldsByName[$name])) {
            return null;
        }

        $field = $this->fieldsByName[$name];

        if ($field['type'] === 'relation') {
            return $this->loadRelationCollection($field);
        }

        $value = $this->valuesByName[$name] ?? null;

        return $this->fieldRegistry->get($field['type'])->renderValue($value);
    }

    public function getId(): int
    {
        return $this->meta['id'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = $this->meta;

        foreach ($this->fieldsByName as $name => $field) {
            if ($field['type'] === 'relation') {
                $related = $this->relationRepository->get($this->meta['id'], $field['id']);
                $data[$name] = $related;

                continue;
            }

            $data[$name] = $this->valuesByName[$name] ?? null;
        }

        return $data;
    }

    public function toJson(int $options = 0): string
    {
        $encoded = wp_json_encode($this->toArray(), $options);

        return is_string($encoded) ? $encoded : '{}';
    }

    /**
     * @param array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>
     * } $field
     */
    private function loadRelationCollection(array $field): EntryCollection
    {
        $relatedIds = $this->relationRepository->get($this->meta['id'], $field['id']);
        $entries = [];

        if ($this->entryLoader === null) {
            return new EntryCollection();
        }

        foreach ($relatedIds as $relatedId) {
            $entry = ($this->entryLoader)($relatedId);

            if ($entry instanceof self) {
                $entries[] = $entry;
            }
        }

        $settings = $field['settings'];
        $multiple = (bool) ($settings['multiple'] ?? false);

        if (! $multiple && count($entries) === 1) {
            return new EntryCollection($entries);
        }

        return new EntryCollection($entries);
    }
}
