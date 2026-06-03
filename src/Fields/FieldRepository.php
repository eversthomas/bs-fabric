<?php

declare(strict_types=1);

namespace BsFabric\Fields;

use InvalidArgumentException;
use RuntimeException;

final class FieldRepository implements FieldAdminOperations
{
    public const SERVICE_ID = 'field.repository';

    public function __construct(
        private readonly FieldRegistry $fieldRegistry,
    ) {
    }

    /**
     * @param array{name: string, label: string, type: string, settings?: array<string, mixed>|null} $data
     */
    public function create(array $data): int
    {
        global $wpdb;

        $name = $this->sanitizeName($data['name'] ?? '');
        $label = $this->sanitizeLabel($data['label'] ?? '');
        $type = sanitize_key($data['type'] ?? '');

        if ($name === '') {
            throw new InvalidArgumentException('Field name is required.');
        }

        if ($label === '') {
            throw new InvalidArgumentException('Field label is required.');
        }

        if (! $this->fieldRegistry->has($type)) {
            throw new InvalidArgumentException(sprintf('Unknown field type: %s', $type));
        }

        if ($this->nameExists($name)) {
            throw new DuplicateFieldNameException(sprintf('Field name already exists: %s', $name));
        }

        $settings = $this->encodeSettings($data['settings'] ?? null);
        $table = $this->fieldsTable();

        $insertData = [
            'name' => $name,
            'label' => $label,
            'type' => $type,
        ];
        $insertFormat = ['%s', '%s', '%s'];

        if ($settings !== null) {
            $insertData['settings'] = $settings;
            $insertFormat[] = '%s';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert($table, $insertData, $insertFormat);

        if ($inserted === false) {
            throw new RuntimeException(
                $wpdb->last_error !== '' ? $wpdb->last_error : 'Failed to create field.'
            );
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param array{label?: string, settings?: array<string, mixed>|null} $data
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid field ID.');
        }

        $existing = $this->getById($id);

        if ($existing === null) {
            throw new FieldNotFoundException(sprintf('Field not found: %d', $id));
        }

        $label = array_key_exists('label', $data)
            ? $this->sanitizeLabel((string) $data['label'])
            : (string) $existing['label'];

        if ($label === '') {
            throw new InvalidArgumentException('Field label is required.');
        }

        $settings = array_key_exists('settings', $data)
            ? $this->encodeSettings($data['settings'])
            : $this->encodeSettings($existing['settings']);

        $table = $this->fieldsTable();
        $updateData = ['label' => $label];
        $updateFormat = ['%s'];

        if ($settings !== null) {
            $updateData['settings'] = $settings;
            $updateFormat[] = '%s';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(
            $table,
            $updateData,
            ['id' => $id],
            $updateFormat,
            ['%d']
        );

        return $updated !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid field ID.');
        }

        if ($this->getById($id) === null) {
            throw new FieldNotFoundException(sprintf('Field not found: %d', $id));
        }

        if ($this->isUsedInTemplate($id)) {
            throw new FieldInUseException(sprintf('Field is in use by a template: %d', $id));
        }

        $table = $this->fieldsTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE id = %d",
                $id
            )
        );

        return $deleted !== false && $deleted > 0;
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>,
     *     created_at: string
     * }|null
     */
    public function getById(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table = $this->fieldsTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name, label, type, settings, created_at FROM {$table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if (! is_array($row)) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>,
     *     created_at: string
     * }>
     */
    public function getAll(): array
    {
        global $wpdb;

        $table = $this->fieldsTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT id, name, label, type, settings, created_at FROM {$table} ORDER BY name ASC",
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
    }

    public function countTemplateUsage(int $fieldId): int
    {
        global $wpdb;

        if ($fieldId <= 0) {
            return 0;
        }

        $table = $wpdb->prefix . 'bs_fabric_template_fields';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE field_id = %d",
                $fieldId
            )
        );

        return (int) $count;
    }

    /**
     * @return array<int, int> field_id => template count
     */
    public function getAllTemplateUsageCounts(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bs_fabric_template_fields';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT field_id, COUNT(*) AS cnt FROM {$table} GROUP BY field_id",
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        $result = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $result[(int) $row['field_id']] = (int) $row['cnt'];
        }

        return $result;
    }

    private function isUsedInTemplate(int $fieldId): bool
    {
        return $this->countTemplateUsage($fieldId) > 0;
    }

    private function nameExists(string $name): bool
    {
        global $wpdb;

        $table = $this->fieldsTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE name = %s",
                $name
            )
        );

        return (int) $count > 0;
    }

    private function fieldsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bs_fabric_fields';
    }

    private function sanitizeName(string $name): string
    {
        return substr(sanitize_key($name), 0, 100);
    }

    private function sanitizeLabel(string $label): string
    {
        return substr(sanitize_text_field($label), 0, 200);
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    private function encodeSettings(mixed $settings): ?string
    {
        if ($settings === null || $settings === []) {
            return null;
        }

        if (! is_array($settings)) {
            return null;
        }

        $encoded = wp_json_encode($settings);

        return is_string($encoded) ? $encoded : null;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>,
     *     created_at: string
     * }
     */
    private function normalizeRow(array $row): array
    {
        $settings = [];

        if (isset($row['settings']) && is_string($row['settings']) && $row['settings'] !== '') {
            $decoded = json_decode($row['settings'], true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'label' => (string) $row['label'],
            'type' => (string) $row['type'],
            'settings' => $settings,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
