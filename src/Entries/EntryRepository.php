<?php

declare(strict_types=1);

namespace BsFabric\Entries;

use BsFabric\Fields\FieldRegistry;
use BsFabric\Fields\FieldRepository;
use BsFabric\Relations\RelationRepository;
use BsFabric\Templates\TemplateNotFoundException;
use BsFabric\Templates\TemplateRepository;
use InvalidArgumentException;
use RuntimeException;

final class EntryRepository
{
    public const SERVICE_ID = 'entry.repository';

    /** @var list<string> */
    private const ALLOWED_STATUSES = ['draft', 'published', 'archived'];

    public function __construct(
        private readonly TemplateRepository $templateRepository,
        private readonly FieldRepository $fieldRepository,
        private readonly FieldRegistry $fieldRegistry,
        private readonly RelationRepository $relationRepository,
    ) {
    }

    /**
     * @param array{
     *     template_id: int,
     *     status?: string,
     *     language?: string,
     *     created_by?: int|null,
     *     fields?: array<string, mixed>
     * } $data
     */
    public function create(array $data): int
    {
        global $wpdb;

        $templateId = (int) ($data['template_id'] ?? 0);

        if ($templateId <= 0) {
            throw new InvalidArgumentException('Template ID is required.');
        }

        $template = $this->templateRepository->getById($templateId);

        if ($template === null) {
            throw new TemplateNotFoundException(sprintf('Template not found: %d', $templateId));
        }

        $status = $this->sanitizeStatus($data['status'] ?? 'draft');
        $language = $this->sanitizeLanguage($data['language'] ?? 'de');
        $createdBy = isset($data['created_by']) ? absint($data['created_by']) : get_current_user_id();
        $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];

        $table = $this->entriesTable();

        if ($createdBy > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $inserted = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (template_id, status, language, created_by) VALUES (%d, %s, %s, %d)",
                    $templateId,
                    $status,
                    $language,
                    $createdBy
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $inserted = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (template_id, status, language) VALUES (%d, %s, %s)",
                    $templateId,
                    $status,
                    $language
                )
            );
        }

        if ($inserted === false) {
            throw new RuntimeException(
                $wpdb->last_error !== '' ? $wpdb->last_error : 'Failed to create entry.'
            );
        }

        $entryId = (int) $wpdb->insert_id;

        /**
         * @param array{
         *     template_id: int,
         *     status?: string,
         *     language?: string,
         *     created_by?: int|null,
         *     fields?: array<string, mixed>
         * } $data
         */
        do_action('bs_fabric_before_entry_save', $entryId, $data);

        $this->saveFieldValues($entryId, $templateId, $fields);
        $this->syncTitle($entryId, $templateId);

        do_action('bs_fabric_after_entry_save', $entryId, $data);

        return $entryId;
    }

    /**
     * @param array{
     *     status?: string,
     *     language?: string,
     *     fields?: array<string, mixed>
     * } $data
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid entry ID.');
        }

        $existing = $this->getEntryRow($id);

        if ($existing === null) {
            throw new EntryNotFoundException(sprintf('Entry not found: %d', $id));
        }

        $templateId = (int) $existing['template_id'];
        $status = array_key_exists('status', $data)
            ? $this->sanitizeStatus((string) $data['status'])
            : (string) $existing['status'];
        $language = array_key_exists('language', $data)
            ? $this->sanitizeLanguage((string) $data['language'])
            : (string) $existing['language'];

        $table = $this->entriesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, language = %s WHERE id = %d",
                $status,
                $language,
                $id
            )
        );

        if ($updated === false) {
            return false;
        }

        /**
         * @param array{
         *     status?: string,
         *     language?: string,
         *     fields?: array<string, mixed>
         * } $data
         */
        do_action('bs_fabric_before_entry_save', $id, $data);

        if (array_key_exists('fields', $data) && is_array($data['fields'])) {
            $this->saveFieldValues($id, $templateId, $data['fields']);
        }

        $this->syncTitle($id, $templateId);

        do_action('bs_fabric_after_entry_save', $id, $data);

        return true;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid entry ID.');
        }

        if ($this->getEntryRow($id) === null) {
            throw new EntryNotFoundException(sprintf('Entry not found: %d', $id));
        }

        do_action('bs_fabric_before_entry_delete', $id);

        $table = $this->entriesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE id = %d",
                $id
            )
        );

        if ($deleted !== false && $deleted > 0) {
            do_action('bs_fabric_after_entry_delete', $id);
        }

        return $deleted !== false && $deleted > 0;
    }

    public function getById(int $id): ?Entry
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->getEntryRow($id);

        if ($row === null) {
            return null;
        }

        return $this->hydrateEntry($row);
    }

    /**
     * @return EntryCollection
     */
    public function getAll(): EntryCollection
    {
        global $wpdb;

        $table = $this->entriesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT id, template_id, title, status, language, created_by, created_at, updated_at
            FROM {$table}
            ORDER BY id ASC",
            ARRAY_A
        );

        if (! is_array($rows)) {
            return new EntryCollection();
        }

        return $this->hydrateCollection($rows);
    }

    /**
     * @return EntryCollection
     */
    public function getByTemplate(int $templateId): EntryCollection
    {
        global $wpdb;

        if ($templateId <= 0) {
            return new EntryCollection();
        }

        $table = $this->entriesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, template_id, title, status, language, created_by, created_at, updated_at
                FROM {$table}
                WHERE template_id = %d
                ORDER BY id ASC",
                $templateId
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return new EntryCollection();
        }

        return $this->hydrateCollection($rows);
    }

    /**
     * Lightweight rows for the Explorer admin screen (no field hydration).
     *
     * @return list<array{
     *     id: int,
     *     title: string,
     *     template_id: int,
     *     template_label: string,
     *     status: string,
     *     created_at: string
     * }>
     */
    public function getExplorerRows(): array
    {
        global $wpdb;

        $entriesTable = $this->entriesTable();
        $templatesTable = $wpdb->prefix . 'bs_fabric_templates';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT e.id, e.title, e.template_id, e.status, e.created_at, t.label AS template_label
            FROM {$entriesTable} e
            INNER JOIN {$templatesTable} t ON t.id = e.template_id
            ORDER BY e.created_at DESC, e.id DESC",
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        $result = [];

        foreach ($rows as $row) {
            $title = isset($row['title']) && is_string($row['title']) && $row['title'] !== ''
                ? (string) $row['title']
                : sprintf('#%d', (int) $row['id']);

            $result[] = [
                'id' => (int) $row['id'],
                'title' => $title,
                'template_id' => (int) $row['template_id'],
                'template_label' => (string) ($row['template_label'] ?? ''),
                'status' => (string) ($row['status'] ?? 'draft'),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $fields keyed by field name
     */
    private function saveFieldValues(int $entryId, int $templateId, array $fields): void
    {
        $templateFields = $this->getTemplateFieldsByName($templateId);

        foreach ($templateFields as $fieldName => $field) {
            if (! array_key_exists($fieldName, $fields)) {
                continue;
            }

            $fieldType = $this->fieldRegistry->get($field['type']);
            $sanitized = $fieldType->sanitizeValue($fields[$fieldName]);

            if ($field['type'] === 'relation') {
                $relatedIds = is_array($sanitized) ? $sanitized : [];
                $this->relationRepository->set($entryId, $field['id'], $relatedIds);

                continue;
            }

            $this->upsertEntryValue($entryId, $field['id'], $sanitized);
        }
    }

    private function upsertEntryValue(int $entryId, int $fieldId, mixed $value): void
    {
        global $wpdb;

        $table = $this->entryValuesTable();
        $encoded = $this->encodeValueForStorage($value);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existingId = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE entry_id = %d AND field_id = %d",
                $entryId,
                $fieldId
            )
        );

        if ($encoded === null) {
            if ($existingId !== null) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$table} WHERE entry_id = %d AND field_id = %d",
                        $entryId,
                        $fieldId
                    )
                );
            }

            return;
        }

        if ($existingId !== null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET value = %s WHERE entry_id = %d AND field_id = %d",
                    $encoded,
                    $entryId,
                    $fieldId
                )
            );

            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (entry_id, field_id, value) VALUES (%d, %d, %s)",
                $entryId,
                $fieldId,
                $encoded
            )
        );
    }

    private function syncTitle(int $entryId, int $templateId): void
    {
        global $wpdb;

        $template = $this->templateRepository->getById($templateId);

        if ($template === null || $template->title_field === null || $template->title_field === '') {
            return;
        }

        $fieldsByName = $this->getTemplateFieldsByName($templateId);
        $titleFieldName = $template->title_field;

        if (! isset($fieldsByName[$titleFieldName])) {
            return;
        }

        $field = $fieldsByName[$titleFieldName];
        $value = $this->loadStoredFieldValue($entryId, $field['id']);
        $title = $this->resolveTitleFromValue($value);
        $table = $this->entriesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET title = %s WHERE id = %d",
                $title,
                $entryId
            )
        );
    }

    private function resolveTitleFromValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_scalar($value)) {
            $title = sanitize_text_field((string) $value);

            return $title !== '' ? substr($title, 0, 500) : null;
        }

        if (is_array($value) && isset($value['label']) && is_scalar($value['label'])) {
            $title = sanitize_text_field((string) $value['label']);

            return $title !== '' ? substr($title, 0, 500) : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateEntry(array $row): Entry
    {
        $templateId = (int) $row['template_id'];
        $entryId = (int) $row['id'];
        $fieldsByName = $this->getTemplateFieldsByName($templateId);
        $valuesByName = $this->loadFieldValues($entryId, $fieldsByName);

        return new Entry(
            $this->normalizeEntryRow($row),
            $fieldsByName,
            $valuesByName,
            $this->fieldRegistry,
            $this->relationRepository,
            fn (int $relatedId): ?Entry => $this->getById($relatedId),
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function hydrateCollection(array $rows): EntryCollection
    {
        $entries = array_map(
            fn (array $row): Entry => $this->hydrateEntry($row),
            $rows
        );

        return new EntryCollection($entries);
    }

    /**
     * @param array<string, array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>
     * }> $fieldsByName
     *
     * @return array<string, mixed>
     */
    private function loadFieldValues(int $entryId, array $fieldsByName): array
    {
        $values = [];

        foreach ($fieldsByName as $fieldName => $field) {
            if ($field['type'] === 'relation') {
                continue;
            }

            $values[$fieldName] = $this->loadStoredFieldValue($entryId, $field['id']);
        }

        return $values;
    }

    private function loadStoredFieldValue(int $entryId, int $fieldId): mixed
    {
        global $wpdb;

        $table = $this->entryValuesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT value FROM {$table} WHERE entry_id = %d AND field_id = %d",
                $entryId,
                $fieldId
            )
        );

        return $this->decodeValueFromStorage(is_string($value) ? $value : null);
    }

    /**
     * @return array<string, array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>
     * }>
     */
    private function getTemplateFieldsByName(int $templateId): array
    {
        $fields = [];

        foreach ($this->templateRepository->getFields($templateId) as $assignment) {
            $field = $this->fieldRepository->getById($assignment['field_id']);

            if ($field === null) {
                continue;
            }

            $fields[$field['name']] = [
                'id' => $field['id'],
                'name' => $field['name'],
                'label' => $field['label'],
                'type' => $field['type'],
                'settings' => $field['settings'],
            ];
        }

        return $fields;
    }

    /**
     * @return array{
     *     id: int,
     *     template_id: int,
     *     title: string|null,
     *     status: string,
     *     language: string,
     *     created_by: int|null,
     *     created_at: string,
     *     updated_at: string
     * }|null
     */
    private function getEntryRow(int $id): ?array
    {
        global $wpdb;

        $table = $this->entriesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, template_id, title, status, language, created_by, created_at, updated_at
                FROM {$table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if (! is_array($row)) {
            return null;
        }

        return $this->normalizeEntryRow($row);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{
     *     id: int,
     *     template_id: int,
     *     title: string|null,
     *     status: string,
     *     language: string,
     *     created_by: int|null,
     *     created_at: string,
     *     updated_at: string
     * }
     */
    private function normalizeEntryRow(array $row): array
    {
        $title = isset($row['title']) && is_string($row['title']) && $row['title'] !== ''
            ? (string) $row['title']
            : null;

        $createdBy = isset($row['created_by']) ? (int) $row['created_by'] : null;

        return [
            'id' => (int) $row['id'],
            'template_id' => (int) $row['template_id'],
            'title' => $title,
            'status' => (string) ($row['status'] ?? 'draft'),
            'language' => (string) ($row['language'] ?? 'de'),
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function sanitizeStatus(string $status): string
    {
        $status = sanitize_key($status);

        return in_array($status, self::ALLOWED_STATUSES, true) ? $status : 'draft';
    }

    private function sanitizeLanguage(string $language): string
    {
        return substr(sanitize_key($language), 0, 10) ?: 'de';
    }

    private function encodeValueForStorage(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $encoded = wp_json_encode($value);

            return is_string($encoded) ? $encoded : null;
        }

        return null;
    }

    private function decodeValueFromStorage(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    private function entriesTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bs_fabric_entries';
    }

    private function entryValuesTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bs_fabric_entry_values';
    }
}
