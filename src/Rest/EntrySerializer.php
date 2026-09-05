<?php

declare(strict_types=1);

namespace BsFabric\Rest;

use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryCollection;

final class EntrySerializer
{
    public const REST_NAMESPACE = 'bs-fabric/v1';

    /** @var list<string> */
    private const SCALAR_FIELD_TYPES = [
        'text',
        'textarea',
        'richtext',
        'number',
        'boolean',
        'date',
        'select',
        'code',
    ];

    /** @var list<string> */
    private array $embedRelations;

    /**
     * @param list<string> $embedRelations
     */
    public function __construct(array $embedRelations = [])
    {
        $this->embedRelations = $embedRelations;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Entry $entry, string $templateName): array
    {
        $data = [
            'id' => $entry->getId(),
            'template' => sanitize_key($templateName),
            'title' => $entry->title,
            'status' => $entry->status,
            'language' => $entry->language,
            'created_at' => $entry->created_at,
            'updated_at' => $entry->updated_at,
            '_links' => $this->buildLinks($entry, $templateName),
        ];

        foreach ($entry->getFields() as $name => $field) {
            if (! empty($field['rest_hidden'])) {
                continue;
            }

            $data[$name] = $this->serializeFieldValue($entry, $name, $field);
        }

        /**
         * @param array<string, mixed> $data
         */
        return apply_filters('bs_fabric_rest_entry', $data, $entry, $templateName);
    }

    /**
     * @param array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>,
     *     rest_hidden: bool
     * } $field
     */
    private function serializeFieldValue(Entry $entry, string $name, array $field): mixed
    {
        $type = (string) $field['type'];

        if ($type === 'relation') {
            return $this->serializeRelationField($entry, $name, $field);
        }

        $value = $entry->{$name};

        if ($type === 'image') {
            return is_array($value) ? $value : null;
        }

        if (in_array($type, self::SCALAR_FIELD_TYPES, true)) {
            return $value;
        }

        return $value;
    }

    /**
     * @param array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>,
     *     rest_hidden: bool
     * } $field
     *
     * @return list<int>|list<array<string, mixed>>
     */
    private function serializeRelationField(Entry $entry, string $name, array $field): array
    {
        if (! in_array($name, $this->embedRelations, true)) {
            return $entry->getRelationIds($name);
        }

        $relatedTemplate = sanitize_key((string) ($field['settings']['template'] ?? ''));

        if ($relatedTemplate === '') {
            return $entry->getRelationIds($name);
        }

        $collection = $entry->{$name};

        if (! $collection instanceof EntryCollection) {
            return $entry->getRelationIds($name);
        }

        $nestedSerializer = new self([]);
        $embedded = [];

        foreach ($collection as $relatedEntry) {
            $embedded[] = $nestedSerializer->serialize($relatedEntry, $relatedTemplate);
        }

        return $embedded;
    }

    /**
     * @return array<string, list<array{href: string}>>
     */
    private function buildLinks(Entry $entry, string $templateName): array
    {
        $templateName = sanitize_key($templateName);
        $entryId = $entry->getId();

        return [
            'self' => [
                [
                    'href' => rest_url(self::REST_NAMESPACE . '/templates/' . $templateName . '/entries/' . $entryId),
                ],
            ],
            'collection' => [
                [
                    'href' => rest_url(self::REST_NAMESPACE . '/templates/' . $templateName . '/entries'),
                ],
            ],
        ];
    }
}
