<?php

declare(strict_types=1);

namespace BsFabric\Entries;

use BsFabric\Core\Plugin;
use BsFabric\Fields\FieldRegistry;
use BsFabric\Frontend\Renderer;
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
     *     updated_by: int|null,
     *     created_at: string,
     *     updated_at: string
     * } $meta
     * @param array<string, array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>,
     *     rest_hidden: bool
     *}> $fieldsByName
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
     * Relation target IDs from storage — independent of whether related entries hydrate.
     *
     * @return list<int>
     */
    public function getRelationIds(string $fieldName): array
    {
        if (! isset($this->fieldsByName[$fieldName])) {
            return [];
        }

        $field = $this->fieldsByName[$fieldName];

        if ($field['type'] !== 'relation') {
            return [];
        }

        return $this->relationRepository->get($this->meta['id'], $field['id']);
    }

    /**
     * @return array<string, array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>,
     *     rest_hidden: bool
     *}>
     */
    public function getFields(): array
    {
        return $this->fieldsByName;
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

    public function render(
        string $view = 'detail',
        ?string $wrapperStart = null,
        ?string $wrapperEnd = null,
    ): string {
        return $this->renderer()->render($this, $view, $wrapperStart, $wrapperEnd);
    }

    private function renderer(): Renderer
    {
        /** @var Renderer $renderer */
        $renderer = Plugin::instance()->container()->get(Renderer::SERVICE_ID);

        return $renderer;
    }

    /**
     * @param array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>,
     *     rest_hidden: bool
     *} $field
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
