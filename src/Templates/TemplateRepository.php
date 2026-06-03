<?php

declare(strict_types=1);

namespace BsFabric\Templates;

use BsFabric\Fields\FieldNotFoundException;
use BsFabric\Fields\FieldRepository;
use InvalidArgumentException;
use RuntimeException;

final class TemplateRepository implements TemplateLookup, TemplateAdminOperations
{
    public const SERVICE_ID = 'template.repository';

    public function __construct(
        private readonly FieldRepository $fieldRepository,
    ) {
    }

    /**
     * @param array{
     *     name: string,
     *     label: string,
     *     title_field?: string|null,
     *     settings?: array<string, mixed>|null
     * } $data
     */
    public function create(array $data): int
    {
        global $wpdb;

        $name = $this->sanitizeName($data['name'] ?? '');
        $label = $this->sanitizeLabel($data['label'] ?? '');
        $titleField = $this->sanitizeTitleField($data['title_field'] ?? null);

        if ($name === '') {
            throw new InvalidArgumentException('Template name is required.');
        }

        if ($label === '') {
            throw new InvalidArgumentException('Template label is required.');
        }

        if ($this->nameExists($name)) {
            throw new DuplicateTemplateNameException(sprintf('Template name already exists: %s', $name));
        }

        $settings = $this->encodeSettings($data['settings'] ?? null);
        $table = $this->templatesTable();

        $insertData = [
            'name' => $name,
            'label' => $label,
        ];
        $insertFormat = ['%s', '%s'];

        if ($titleField !== null && $titleField !== '') {
            $insertData['title_field'] = $titleField;
            $insertFormat[] = '%s';
        }

        if ($settings !== null) {
            $insertData['settings'] = $settings;
            $insertFormat[] = '%s';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert($table, $insertData, $insertFormat);

        if ($inserted === false) {
            throw new RuntimeException(
                $wpdb->last_error !== '' ? $wpdb->last_error : 'Failed to create template.'
            );
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param array{
     *     label?: string,
     *     title_field?: string|null,
     *     settings?: array<string, mixed>|null
     * } $data
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid template ID.');
        }

        $existing = $this->getById($id);

        if ($existing === null) {
            throw new TemplateNotFoundException(sprintf('Template not found: %d', $id));
        }

        $label = array_key_exists('label', $data)
            ? $this->sanitizeLabel((string) $data['label'])
            : $existing->label;

        if ($label === '') {
            throw new InvalidArgumentException('Template label is required.');
        }

        $titleField = array_key_exists('title_field', $data)
            ? $this->sanitizeTitleField($data['title_field'])
            : $existing->title_field;

        if ($titleField !== null && $titleField !== '' && ! $this->templateHasFieldName($id, $titleField)) {
            throw new InvalidArgumentException('Title field must be assigned to the template.');
        }

        $settings = array_key_exists('settings', $data)
            ? $this->encodeSettings($data['settings'])
            : $this->encodeSettings($existing->settings);

        $table = $this->templatesTable();
        $updateData = [
            'label' => $label,
            'title_field' => $titleField !== '' ? $titleField : null,
        ];
        $updateFormat = ['%s', '%s'];

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
            throw new InvalidArgumentException('Invalid template ID.');
        }

        if ($this->getById($id) === null) {
            throw new TemplateNotFoundException(sprintf('Template not found: %d', $id));
        }

        if ($this->hasEntries($id)) {
            throw new TemplateInUseException(sprintf('Template has entries and cannot be deleted: %d', $id));
        }

        $table = $this->templatesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE id = %d",
                $id
            )
        );

        return $deleted !== false && $deleted > 0;
    }

    public function getById(int $id): ?Template
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table = $this->templatesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name, label, title_field, settings, created_at FROM {$table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if (! is_array($row)) {
            return null;
        }

        return new Template($this->normalizeTemplateRow($row));
    }

    public function getByName(string $name): ?Template
    {
        global $wpdb;

        $name = $this->sanitizeName($name);

        if ($name === '') {
            return null;
        }

        $table = $this->templatesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name, label, title_field, settings, created_at FROM {$table} WHERE name = %s",
                $name
            ),
            ARRAY_A
        );

        if (! is_array($row)) {
            return null;
        }

        return new Template($this->normalizeTemplateRow($row));
    }

    /**
     * @return list<Template>
     */
    public function getAll(): array
    {
        global $wpdb;

        $table = $this->templatesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT id, name, label, title_field, settings, created_at FROM {$table} ORDER BY name ASC",
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        return array_map(
            fn (array $row): Template => new Template($this->normalizeTemplateRow($row)),
            $rows
        );
    }

    public function assignField(int $templateId, int $fieldId, bool $isRequired = false): bool
    {
        global $wpdb;

        $this->assertTemplateExists($templateId);

        if ($this->fieldRepository->getById($fieldId) === null) {
            throw new FieldNotFoundException(sprintf('Field not found: %d', $fieldId));
        }

        if ($this->isFieldAssigned($templateId, $fieldId)) {
            return true;
        }

        $table = $this->templateFieldsTable();
        $sortOrder = $this->getNextSortOrder($templateId);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (template_id, field_id, sort_order, is_required) VALUES (%d, %d, %d, %d)",
                $templateId,
                $fieldId,
                $sortOrder,
                $isRequired ? 1 : 0
            )
        );

        return $inserted !== false;
    }

    public function removeField(int $templateId, int $fieldId): bool
    {
        global $wpdb;

        $this->assertTemplateExists($templateId);

        $table = $this->templateFieldsTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE template_id = %d AND field_id = %d",
                $templateId,
                $fieldId
            )
        );

        if ($deleted !== false && $deleted > 0) {
            $this->clearTitleFieldIfRemoved($templateId, $fieldId);
        }

        return $deleted !== false && $deleted > 0;
    }

    /**
     * @return list<array{
     *     assignment_id: int,
     *     field_id: int,
     *     sort_order: int,
     *     is_required: bool,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>
     * }>
     */
    public function getFields(int $templateId): array
    {
        global $wpdb;

        if ($this->getById($templateId) === null) {
            throw new TemplateNotFoundException(sprintf('Template not found: %d', $templateId));
        }

        $templateFieldsTable = $this->templateFieldsTable();
        $fieldsTable = $wpdb->prefix . 'bs_fabric_fields';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT tf.id AS assignment_id, tf.field_id, tf.sort_order, tf.is_required,
                        f.name, f.label, f.type, f.settings
                 FROM {$templateFieldsTable} tf
                 INNER JOIN {$fieldsTable} f ON f.id = tf.field_id
                 WHERE tf.template_id = %d
                 ORDER BY tf.sort_order ASC, tf.id ASC",
                $templateId
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        return array_map(
            fn (array $row): array => [
                'assignment_id' => (int) $row['assignment_id'],
                'field_id' => (int) $row['field_id'],
                'sort_order' => (int) $row['sort_order'],
                'is_required' => (int) $row['is_required'] === 1,
                'name' => (string) $row['name'],
                'label' => (string) $row['label'],
                'type' => (string) $row['type'],
                'settings' => $this->decodeFieldSettings($row['settings'] ?? null),
            ],
            $rows
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeFieldSettings(mixed $settings): array
    {
        if (! is_string($settings) || $settings === '') {
            return [];
        }

        $decoded = json_decode($settings, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param list<int> $fieldIds
     */
    public function updateFieldOrder(int $templateId, array $fieldIds): bool
    {
        global $wpdb;

        $this->assertTemplateExists($templateId);

        $table = $this->templateFieldsTable();

        foreach (array_values($fieldIds) as $index => $fieldId) {
            $fieldId = (int) $fieldId;

            if ($fieldId <= 0) {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $updated = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET sort_order = %d WHERE template_id = %d AND field_id = %d",
                    $index,
                    $templateId,
                    $fieldId
                )
            );

            if ($updated === false) {
                return false;
            }
        }

        return true;
    }

    public function updateFieldRequired(int $templateId, int $fieldId, bool $isRequired): bool
    {
        global $wpdb;

        $this->assertTemplateExists($templateId);

        $table = $this->templateFieldsTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET is_required = %d WHERE template_id = %d AND field_id = %d",
                $isRequired ? 1 : 0,
                $templateId,
                $fieldId
            )
        );

        return $updated !== false;
    }

    private function assertTemplateExists(int $templateId): void
    {
        if ($this->getById($templateId) === null) {
            throw new TemplateNotFoundException(sprintf('Template not found: %d', $templateId));
        }
    }

    private function hasEntries(int $templateId): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bs_fabric_entries';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE template_id = %d",
                $templateId
            )
        );

        return (int) $count > 0;
    }

    private function isFieldAssigned(int $templateId, int $fieldId): bool
    {
        global $wpdb;

        $table = $this->templateFieldsTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE template_id = %d AND field_id = %d",
                $templateId,
                $fieldId
            )
        );

        return (int) $count > 0;
    }

    private function getNextSortOrder(int $templateId): int
    {
        global $wpdb;

        $table = $this->templateFieldsTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $max = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(sort_order) FROM {$table} WHERE template_id = %d",
                $templateId
            )
        );

        return $max === null ? 0 : ((int) $max + 1);
    }

    private function templateHasFieldName(int $templateId, string $fieldName): bool
    {
        foreach ($this->getFields($templateId) as $field) {
            if ($field['name'] === $fieldName) {
                return true;
            }
        }

        return false;
    }

    private function clearTitleFieldIfRemoved(int $templateId, int $fieldId): void
    {
        $template = $this->getById($templateId);

        if ($template === null || $template->title_field === null || $template->title_field === '') {
            return;
        }

        $field = $this->fieldRepository->getById($fieldId);

        if ($field !== null && $field['name'] === $template->title_field) {
            $this->update($templateId, ['title_field' => null]);
        }
    }

    private function nameExists(string $name): bool
    {
        global $wpdb;

        $table = $this->templatesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE name = %s",
                $name
            )
        );

        return (int) $count > 0;
    }

    private function templatesTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bs_fabric_templates';
    }

    private function templateFieldsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bs_fabric_template_fields';
    }

    private function sanitizeName(string $name): string
    {
        return substr(sanitize_key($name), 0, 100);
    }

    private function sanitizeLabel(string $label): string
    {
        return substr(sanitize_text_field($label), 0, 200);
    }

    private function sanitizeTitleField(mixed $titleField): ?string
    {
        if ($titleField === null || $titleField === '') {
            return null;
        }

        if (! is_scalar($titleField)) {
            return null;
        }

        return substr(sanitize_key((string) $titleField), 0, 100);
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
     *     title_field: string|null,
     *     settings: array<string, mixed>,
     *     created_at: string
     * }
     */
    private function normalizeTemplateRow(array $row): array
    {
        $settings = [];

        if (isset($row['settings']) && is_string($row['settings']) && $row['settings'] !== '') {
            $decoded = json_decode($row['settings'], true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        $titleField = isset($row['title_field']) && is_string($row['title_field']) && $row['title_field'] !== ''
            ? (string) $row['title_field']
            : null;

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'label' => (string) $row['label'],
            'title_field' => $titleField,
            'settings' => $settings,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
