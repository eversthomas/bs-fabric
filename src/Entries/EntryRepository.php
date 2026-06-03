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

final class EntryRepository implements EntryQueryExecutor, EntryAdminOperations
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

    /** @var array<int, Entry> */
    private array $runtimeCache = [];

    /** @var array<int, array<string, array{id: int, name: string, label: string, type: string, settings: array<string, mixed>}>> */
    private array $templateFieldsCache = [];

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

        $this->beginTransaction();

        try {
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

            $this->commitTransaction();
        } catch (\Throwable $exception) {
            $this->rollbackTransaction();

            throw $exception;
        }

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

        $this->beginTransaction();

        try {
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
                $this->rollbackTransaction();

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

            $this->commitTransaction();
        } catch (\Throwable $exception) {
            $this->rollbackTransaction();

            throw $exception;
        }

        do_action('bs_fabric_after_entry_save', $id, $data);

        return true;
    }

    public function getTemplateIdForEntry(int $id): ?int
    {
        $row = $this->getEntryRow($id);

        if ($row === null) {
            return null;
        }

        return (int) $row['template_id'];
    }

    public function updateStatus(int $id, string $status): bool
    {
        return $this->update($id, ['status' => $status]);
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

    public function duplicate(int $id): int
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid entry ID.');
        }

        $row = $this->getEntryRow($id);

        if ($row === null) {
            throw new EntryNotFoundException(sprintf('Entry not found: %d', $id));
        }

        $templateId = (int) $row['template_id'];
        $template = $this->templateRepository->getById($templateId);

        if ($template === null) {
            throw new TemplateNotFoundException(sprintf('Template not found: %d', $templateId));
        }

        $fieldsByName = $this->getTemplateFieldsByName($templateId);
        $fieldValues = $this->loadFieldValues($id, $fieldsByName);

        foreach ($fieldsByName as $fieldName => $field) {
            if ($field['type'] !== 'relation') {
                continue;
            }

            $fieldValues[$fieldName] = $this->relationRepository->get($id, $field['id']);
        }

        $titleField = is_string($template->title_field) ? $template->title_field : '';

        if ($titleField !== '' && isset($fieldValues[$titleField]) && is_string($fieldValues[$titleField])) {
            $fieldValues[$titleField] .= ' ' . __('(Kopie)', 'bs-fabric');
        }

        return $this->create([
            'template_id' => $templateId,
            'status' => 'draft',
            'language' => (string) $row['language'],
            'fields' => $fieldValues,
        ]);
    }

    public function deleteAll(): void
    {
        global $wpdb;

        $relationsTable = $wpdb->prefix . 'bs_fabric_relations';
        $valuesTable = $wpdb->prefix . 'bs_fabric_entry_values';
        $entriesTable = $this->entriesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("DELETE FROM {$relationsTable}");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("DELETE FROM {$valuesTable}");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("DELETE FROM {$entriesTable}");
    }

    public function getById(int $id): ?Entry
    {
        if ($id <= 0) {
            return null;
        }

        if (isset($this->runtimeCache[$id])) {
            return $this->runtimeCache[$id];
        }

        $row = $this->getEntryRow($id);

        if ($row === null) {
            return null;
        }

        $entry = $this->hydrateEntry($row);
        $this->runtimeCache[$id] = $entry;

        return $entry;
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
        $fieldsTable = $wpdb->prefix . 'bs_fabric_fields';
        $entryValuesTable = $this->entryValuesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT e.id, e.title, e.template_id, e.status, e.created_at, t.label AS template_label,
                    ev.value AS title_field_value
            FROM {$entriesTable} e
            INNER JOIN {$templatesTable} t ON t.id = e.template_id
            LEFT JOIN {$fieldsTable} f
                ON f.name = t.title_field
                AND t.title_field IS NOT NULL
                AND t.title_field <> ''
            LEFT JOIN {$entryValuesTable} ev ON ev.entry_id = e.id AND ev.field_id = f.id
            ORDER BY e.created_at DESC, e.id DESC",
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $result[] = $this->mapExplorerRow($row);
        }

        return $result;
    }

    /**
     * @param array{
     *     page?: int,
     *     per_page?: int,
     *     search?: string,
     *     template_id?: int,
     *     status?: string,
     *     sort_field?: string,
     *     sort_direction?: string
     * } $args
     *
     * @return array{
     *     rows: list<array{
     *         id: int,
     *         title: string,
     *         template_id: int,
     *         template_label: string,
     *         status: string,
     *         created_at: string
     *     }>,
     *     total: int,
     *     total_pages: int
     * }
     */
    public function queryExplorerRows(array $args): array
    {
        global $wpdb;

        $page = max(1, (int) ($args['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($args['per_page'] ?? 20)));
        $search = sanitize_text_field((string) ($args['search'] ?? ''));
        $templateId = max(0, (int) ($args['template_id'] ?? 0));
        $status = sanitize_key((string) ($args['status'] ?? ''));

        if ($status !== '' && ! in_array($status, self::ALLOWED_STATUSES, true)) {
            $status = '';
        }

        $sortField = sanitize_key((string) ($args['sort_field'] ?? 'created_at'));
        $sortDirection = strtoupper((string) ($args['sort_direction'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $entriesTable = $this->entriesTable();
        $templatesTable = $wpdb->prefix . 'bs_fabric_templates';
        $fieldsTable = $wpdb->prefix . 'bs_fabric_fields';
        $entryValuesTable = $this->entryValuesTable();

        $joinSql = "INNER JOIN {$templatesTable} t ON t.id = e.template_id
            LEFT JOIN {$fieldsTable} f
                ON f.name = t.title_field
                AND t.title_field IS NOT NULL
                AND t.title_field <> ''
            LEFT JOIN {$entryValuesTable} ev ON ev.entry_id = e.id AND ev.field_id = f.id";

        $where = ['1=1'];
        $params = [];

        if ($templateId > 0) {
            $where[] = 'e.template_id = %d';
            $params[] = $templateId;
        }

        if ($status !== '') {
            $where[] = 'e.status = %s';
            $params[] = $status;
        }

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(e.title LIKE %s OR CAST(e.id AS CHAR) LIKE %s OR ev.value LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = implode(' AND ', $where);
        $fromSql = "FROM {$entriesTable} e {$joinSql} WHERE {$whereSql}";

        if ($params === []) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $total = (int) $wpdb->get_var("SELECT COUNT(DISTINCT e.id) {$fromSql}");
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT e.id) {$fromSql}", ...$params));
        }

        $orderSql = match ($sortField) {
            'title' => "e.title {$sortDirection}, e.id {$sortDirection}",
            'status' => "e.status {$sortDirection}, e.id {$sortDirection}",
            'template_id' => "t.label {$sortDirection}, e.id {$sortDirection}",
            default => "e.created_at {$sortDirection}, e.id {$sortDirection}",
        };

        $offset = ($page - 1) * $perPage;
        $selectSql = "SELECT e.id, e.title, e.template_id, e.status, e.created_at, t.label AS template_label,
                ev.value AS title_field_value {$fromSql}
            ORDER BY {$orderSql}
            LIMIT %d OFFSET %d";

        $queryParams = array_merge($params, [$perPage, $offset]);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare($selectSql, ...$queryParams),
            ARRAY_A
        );

        $result = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $result[] = $this->mapExplorerRow($row);
            }
        }

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'rows' => $result,
            'total' => $total,
            'total_pages' => max(1, $totalPages),
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     template_id: int,
     *     template_label: string,
     *     status: string,
     *     created_at: string
     * }
     */
    private function mapExplorerRow(array $row): array
    {
        $entryId = (int) $row['id'];
        $templateId = (int) $row['template_id'];
        $storedTitle = isset($row['title']) && is_string($row['title']) && $row['title'] !== ''
            ? (string) $row['title']
            : null;
        $titleFieldValue = isset($row['title_field_value']) && is_string($row['title_field_value'])
            ? $row['title_field_value']
            : null;
        $title = $this->resolveDisplayTitle($entryId, $templateId, $storedTitle, $titleFieldValue);

        return [
            'id' => $entryId,
            'title' => $title,
            'template_id' => $templateId,
            'template_label' => (string) ($row['template_label'] ?? ''),
            'status' => (string) ($row['status'] ?? 'draft'),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    public function countAll(): int
    {
        global $wpdb;

        $table = $this->entriesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        return (int) $count;
    }

    public function countByStatus(string $status): int
    {
        global $wpdb;

        $status = $this->sanitizeStatus($status);
        $table = $this->entriesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = %s",
                $status
            )
        );

        return (int) $count;
    }

    /**
     * Lightweight rows for the dashboard „Zuletzt bearbeitet“ list.
     *
     * @return list<array{
     *     id: int,
     *     title: string,
     *     template_id: int,
     *     template_label: string,
     *     status: string,
     *     updated_at: string,
     *     editor_name: string
     * }>
     */
    public function getRecentlyUpdated(int $limit = 5): array
    {
        global $wpdb;

        $limit = max(1, min($limit, 20));
        $entriesTable = $this->entriesTable();
        $templatesTable = $wpdb->prefix . 'bs_fabric_templates';
        $fieldsTable = $wpdb->prefix . 'bs_fabric_fields';
        $entryValuesTable = $this->entryValuesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.id, e.title, e.template_id, e.status, e.updated_at, e.created_by,
                        t.label AS template_label, ev.value AS title_field_value
                FROM {$entriesTable} e
                INNER JOIN {$templatesTable} t ON t.id = e.template_id
                LEFT JOIN {$fieldsTable} f
                    ON f.name = t.title_field
                    AND t.title_field IS NOT NULL
                    AND t.title_field <> ''
                LEFT JOIN {$entryValuesTable} ev ON ev.entry_id = e.id AND ev.field_id = f.id
                ORDER BY e.updated_at DESC, e.id DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        $result = [];

        foreach ($rows as $row) {
            $entryId = (int) $row['id'];
            $templateId = (int) $row['template_id'];
            $storedTitle = isset($row['title']) && is_string($row['title']) && $row['title'] !== ''
                ? (string) $row['title']
                : null;
            $titleFieldValue = isset($row['title_field_value']) && is_string($row['title_field_value'])
                ? $row['title_field_value']
                : null;
            $title = $this->resolveDisplayTitle($entryId, $templateId, $storedTitle, $titleFieldValue);

            $createdBy = isset($row['created_by']) ? (int) $row['created_by'] : 0;
            $editorName = $createdBy > 0
                ? (string) get_the_author_meta('display_name', $createdBy)
                : '';

            if ($editorName === '') {
                $editorName = __('Unbekannt', 'bs-fabric');
            }

            $result[] = [
                'id' => $entryId,
                'title' => $title,
                'template_id' => $templateId,
                'template_label' => (string) ($row['template_label'] ?? ''),
                'status' => (string) ($row['status'] ?? 'draft'),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'editor_name' => $editorName,
            ];
        }

        return $result;
    }

    public function resyncTitlesForTemplate(int $templateId): void
    {
        global $wpdb;

        if ($templateId <= 0) {
            return;
        }

        $table = $this->entriesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $entryIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE template_id = %d",
                $templateId
            )
        );

        if (! is_array($entryIds)) {
            return;
        }

        foreach ($entryIds as $entryId) {
            $this->syncTitle((int) $entryId, $templateId);
        }
    }

    /**
     * @param array{
     *     template_id: int,
     *     wheres: list<array{field: string, value: mixed}>,
     *     with: list<string>,
     *     status: string|null,
     *     order_by: string|null,
     *     order_direction: string,
     *     limit: int|null
     * } $query
     */
    public function findByFrontendQuery(array $query): EntryCollection
    {
        $entryIds = $this->fetchFrontendQueryIds($query);

        if ($entryIds === []) {
            return new EntryCollection();
        }

        if ($query['with'] !== []) {
            $this->eagerLoadRelations($entryIds, (int) $query['template_id'], $query['with']);
        }

        return $this->collectEntriesByIds($entryIds);
    }

    /**
     * @param array{
     *     template_id: int,
     *     wheres: list<array{field: string, value: mixed}>,
     *     with: list<string>,
     *     status: string|null,
     *     order_by: string|null,
     *     order_direction: string,
     *     limit: int|null
     * } $query
     */
    public function countByFrontendQuery(array $query): int
    {
        global $wpdb;

        [$sql, $params] = $this->buildFrontendQuerySql($query, true);

        if ($params === []) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $count = $wpdb->get_var($sql);
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $count = $wpdb->get_var($wpdb->prepare($sql, ...$params));
        }

        return max(0, (int) $count);
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

                if (! $this->relationRepository->set($entryId, $field['id'], $relatedIds)) {
                    throw new RuntimeException('Failed to save relation field values.');
                }

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
                $deleted = $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$table} WHERE entry_id = %d AND field_id = %d",
                        $entryId,
                        $fieldId
                    )
                );

                if ($deleted === false) {
                    throw new RuntimeException(
                        $wpdb->last_error !== '' ? $wpdb->last_error : 'Failed to delete entry value.'
                    );
                }
            }

            return;
        }

        if ($existingId !== null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $updated = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET value = %s WHERE entry_id = %d AND field_id = %d",
                    $encoded,
                    $entryId,
                    $fieldId
                )
            );

            if ($updated === false) {
                throw new RuntimeException(
                    $wpdb->last_error !== '' ? $wpdb->last_error : 'Failed to update entry value.'
                );
            }

            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (entry_id, field_id, value) VALUES (%d, %d, %s)",
                $entryId,
                $fieldId,
                $encoded
            )
        );

        if ($inserted === false) {
            throw new RuntimeException(
                $wpdb->last_error !== '' ? $wpdb->last_error : 'Failed to insert entry value.'
            );
        }
    }

    private function syncTitle(int $entryId, int $templateId): void
    {
        $title = $this->resolveDisplayTitle($entryId, $templateId, null);
        $this->persistTitle($entryId, str_starts_with($title, '#') ? null : $title);
    }

    private function resolveDisplayTitle(
        int $entryId,
        int $templateId,
        ?string $storedTitle,
        ?string $prefetchedTitleFieldValue = null,
    ): string {
        $title = $this->resolveTitleFromValue($storedTitle);

        if ($title !== null) {
            return $title;
        }

        if ($prefetchedTitleFieldValue !== null) {
            $title = $this->resolveTitleFromValue(
                $this->decodeValueFromStorage($prefetchedTitleFieldValue)
            );

            if ($title !== null) {
                return $title;
            }
        }

        $template = $this->templateRepository->getById($templateId);

        if ($template === null || $template->title_field === null || $template->title_field === '') {
            return sprintf('#%d', $entryId);
        }

        $fieldsByName = $this->getTemplateFieldsByName($templateId);
        $titleFieldName = $template->title_field;

        if (! isset($fieldsByName[$titleFieldName])) {
            return sprintf('#%d', $entryId);
        }

        $value = $this->loadStoredFieldValue($entryId, $fieldsByName[$titleFieldName]['id']);
        $title = $this->resolveTitleFromValue($value);

        return $title ?? sprintf('#%d', $entryId);
    }

    private function maybeBackfillStoredTitle(int $entryId, ?string $storedTitle, string $resolvedTitle): void
    {
        if ($storedTitle !== null && $storedTitle !== '') {
            return;
        }

        if (str_starts_with($resolvedTitle, '#')) {
            return;
        }

        $this->persistTitle($entryId, $resolvedTitle);
    }

    private function persistTitle(int $entryId, ?string $title): void
    {
        global $wpdb;

        $table = $this->entriesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET title = %s WHERE id = %d",
                $title,
                $entryId
            )
        );

        if ($updated === false) {
            throw new RuntimeException(
                $wpdb->last_error !== '' ? $wpdb->last_error : 'Failed to persist entry title.'
            );
        }
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
        $meta = $this->normalizeEntryRow($row);
        $templateId = (int) $meta['template_id'];
        $entryId = (int) $meta['id'];
        $fieldsByName = $this->getTemplateFieldsByName($templateId);
        $valuesByName = $this->loadFieldValues($entryId, $fieldsByName);

        return $this->buildEntry($meta, $fieldsByName, $valuesByName);
    }

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
    private function buildEntry(array $meta, array $fieldsByName, array $valuesByName): Entry
    {
        $entryId = (int) $meta['id'];
        $templateId = (int) $meta['template_id'];
        $storedTitle = is_string($meta['title']) ? $meta['title'] : null;
        $resolvedTitle = $this->resolveDisplayTitle($entryId, $templateId, $storedTitle);
        $meta['title'] = $resolvedTitle !== sprintf('#%d', $entryId) ? $resolvedTitle : null;

        return new Entry(
            $meta,
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
        if ($rows === []) {
            return new EntryCollection();
        }

        $normalizedRows = array_map(
            fn (array $row): array => $this->normalizeEntryRow($row),
            $rows
        );

        $entryIds = array_map(
            static fn (array $row): int => (int) $row['id'],
            $normalizedRows
        );

        $templateIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['template_id'],
            $normalizedRows
        )));

        $fieldsByTemplate = [];

        foreach ($templateIds as $templateId) {
            $fieldsByTemplate[$templateId] = $this->getTemplateFieldsByName($templateId);
        }

        $batchValues = $this->loadStoredFieldValuesBatch($entryIds);
        $entries = [];

        foreach ($normalizedRows as $meta) {
            $templateId = (int) $meta['template_id'];
            $entryId = (int) $meta['id'];
            $fieldsByName = $fieldsByTemplate[$templateId];
            $valuesByName = $this->mapBatchValuesToFieldNames($entryId, $fieldsByName, $batchValues);
            $entries[] = $this->buildEntry($meta, $fieldsByName, $valuesByName);
        }

        return new EntryCollection($entries);
    }

    /**
     * @param list<int> $entryIds
     */
    private function collectEntriesByIds(array $entryIds): EntryCollection
    {
        $entryIds = array_values(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $entryIds),
            static fn (int $id): bool => $id > 0
        ));

        if ($entryIds === []) {
            return new EntryCollection();
        }

        $ordered = [];
        $missing = [];

        foreach ($entryIds as $entryId) {
            if (isset($this->runtimeCache[$entryId])) {
                $ordered[$entryId] = $this->runtimeCache[$entryId];

                continue;
            }

            $missing[] = $entryId;
        }

        if ($missing !== []) {
            global $wpdb;

            $table = $this->entriesTable();
            $placeholders = implode(', ', array_fill(0, count($missing), '%d'));

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, template_id, title, status, language, created_by, created_at, updated_at
                    FROM {$table}
                    WHERE id IN ({$placeholders})",
                    ...$missing
                ),
                ARRAY_A
            );

            if (is_array($rows)) {
                foreach ($this->hydrateCollection($rows) as $entry) {
                    $this->runtimeCache[$entry->getId()] = $entry;
                    $ordered[$entry->getId()] = $entry;
                }
            }
        }

        $result = [];

        foreach ($entryIds as $entryId) {
            if (isset($ordered[$entryId])) {
                $result[] = $ordered[$entryId];
            }
        }

        return new EntryCollection($result);
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
        return $this->mapBatchValuesToFieldNames(
            $entryId,
            $fieldsByName,
            $this->loadStoredFieldValuesBatch([$entryId])
        );
    }

    /**
     * @param list<int> $entryIds
     *
     * @return array<int, array<int, mixed>>
     */
    private function loadStoredFieldValuesBatch(array $entryIds): array
    {
        global $wpdb;

        $entryIds = array_values(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $entryIds),
            static fn (int $id): bool => $id > 0
        ));

        if ($entryIds === []) {
            return [];
        }

        $table = $this->entryValuesTable();
        $placeholders = implode(', ', array_fill(0, count($entryIds), '%d'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT entry_id, field_id, value FROM {$table} WHERE entry_id IN ({$placeholders})",
                ...$entryIds
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $entryId = (int) $row['entry_id'];
            $fieldId = (int) $row['field_id'];
            $rawValue = isset($row['value']) && is_string($row['value']) ? $row['value'] : null;
            $map[$entryId][$fieldId] = $this->decodeValueFromStorage($rawValue);
        }

        return $map;
    }

    /**
     * @param array<string, array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>
     * }> $fieldsByName
     * @param array<int, array<int, mixed>> $batchValues
     *
     * @return array<string, mixed>
     */
    private function mapBatchValuesToFieldNames(int $entryId, array $fieldsByName, array $batchValues): array
    {
        $entryValues = $batchValues[$entryId] ?? [];
        $values = [];

        foreach ($fieldsByName as $fieldName => $field) {
            if ($field['type'] === 'relation') {
                continue;
            }

            $values[$fieldName] = $entryValues[$field['id']] ?? null;
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
        if (isset($this->templateFieldsCache[$templateId])) {
            return $this->templateFieldsCache[$templateId];
        }

        $fields = [];

        foreach ($this->templateRepository->getFields($templateId) as $assignment) {
            $fields[$assignment['name']] = [
                'id' => $assignment['field_id'],
                'name' => $assignment['name'],
                'label' => $assignment['label'],
                'type' => $assignment['type'],
                'settings' => $assignment['settings'],
            ];
        }

        $this->templateFieldsCache[$templateId] = $fields;

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

    /**
     * @param array{
     *     template_id: int,
     *     wheres: list<array{field: string, value: mixed}>,
     *     with: list<string>,
     *     status: string|null,
     *     order_by: string|null,
     *     order_direction: string,
     *     limit: int|null
     * } $query
     *
     * @return list<int>
     */
    private function fetchFrontendQueryIds(array $query): array
    {
        global $wpdb;

        [$sql, $params] = $this->buildFrontendQuerySql($query, false);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $params === []
            ? $wpdb->get_col($sql)
            : $wpdb->get_col($wpdb->prepare($sql, ...$params));

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $id): int => (int) $id, $rows));
    }

    /**
     * @param array{
     *     template_id: int,
     *     wheres: list<array{field: string, value: mixed}>,
     *     with: list<string>,
     *     status: string|null,
     *     order_by: string|null,
     *     order_direction: string,
     *     limit: int|null
     * } $query
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildFrontendQuerySql(array $query, bool $forCount): array
    {
        global $wpdb;

        $entriesTable = $this->entriesTable();
        $valuesTable = $this->entryValuesTable();
        $fieldsTable = $wpdb->prefix . 'bs_fabric_fields';
        $joins = [];
        $where = ['e.template_id = %d'];
        $params = [(int) $query['template_id']];

        foreach ($query['wheres'] as $index => $whereClause) {
            $valueAlias = 'ev' . $index;
            $fieldAlias = 'fv' . $index;
            $joins[] = "INNER JOIN {$valuesTable} {$valueAlias} ON {$valueAlias}.entry_id = e.id";
            $joins[] = "INNER JOIN {$fieldsTable} {$fieldAlias} ON {$fieldAlias}.id = {$valueAlias}.field_id AND {$fieldAlias}.name = %s";
            $where[] = "{$valueAlias}.value = %s";
            $params[] = $whereClause['field'];
            $params[] = $this->encodeValueForWhere($whereClause['value']);
        }

        if ($query['status'] !== null && $query['status'] !== '') {
            $where[] = 'e.status = %s';
            $params[] = $query['status'];
        }

        $direction = $query['order_direction'] === 'DESC' ? 'DESC' : 'ASC';
        $orderSql = "e.created_at {$direction}, e.id {$direction}";

        if ($query['order_by'] !== null && $query['order_by'] !== '') {
            $orderField = $query['order_by'];

            if (in_array($orderField, ['id', 'title', 'status', 'created_at', 'updated_at'], true)) {
                $orderSql = "e.{$orderField} {$direction}";
            } else {
                $joins[] = "LEFT JOIN {$valuesTable} ev_order ON ev_order.entry_id = e.id";
                $joins[] = "LEFT JOIN {$fieldsTable} f_order ON f_order.id = ev_order.field_id AND f_order.name = %s";
                $params[] = $orderField;
                $orderSql = "ev_order.value {$direction}";
            }
        }

        $select = $forCount ? 'COUNT(DISTINCT e.id)' : 'DISTINCT e.id';
        $sql = 'SELECT ' . $select . " FROM {$entriesTable} e "
            . implode(' ', $joins)
            . ' WHERE '
            . implode(' AND ', $where);

        if (! $forCount) {
            $sql .= ' ORDER BY ' . $orderSql;

            if ($query['limit'] !== null) {
                $sql .= ' LIMIT %d';
                $params[] = (int) $query['limit'];
            }
        }

        return [$sql, $params];
    }

    /**
     * @param list<int> $entryIds
     * @param list<string> $relationFields
     */
    private function eagerLoadRelations(array $entryIds, int $templateId, array $relationFields): void
    {
        $fieldsByName = $this->getTemplateFieldsByName($templateId);
        $relatedIds = [];

        foreach ($relationFields as $fieldName) {
            if (! isset($fieldsByName[$fieldName]) || $fieldsByName[$fieldName]['type'] !== 'relation') {
                continue;
            }

            $map = $this->relationRepository->getBatch($entryIds, $fieldsByName[$fieldName]['id']);

            foreach ($map as $ids) {
                $relatedIds = array_merge($relatedIds, $ids);
            }
        }

        $relatedIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $relatedIds),
            static fn (int $id): bool => $id > 0 && ! isset($this->runtimeCache[$id])
        )));

        if ($relatedIds === []) {
            return;
        }

        $this->warmRuntimeCache($relatedIds);
    }

    /**
     * @param list<int> $entryIds
     */
    private function warmRuntimeCache(array $entryIds): void
    {
        global $wpdb;

        $entryIds = array_values(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $entryIds),
            fn (int $id): bool => $id > 0 && ! isset($this->runtimeCache[$id])
        ));

        if ($entryIds === []) {
            return;
        }

        $table = $this->entriesTable();
        $placeholders = implode(', ', array_fill(0, count($entryIds), '%d'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, template_id, title, status, language, created_by, created_at, updated_at
                FROM {$table}
                WHERE id IN ({$placeholders})",
                ...$entryIds
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return;
        }

        $collection = $this->hydrateCollection($rows);

        foreach ($collection as $entry) {
            $this->runtimeCache[$entry->getId()] = $entry;
        }
    }

    private function encodeValueForWhere(mixed $value): string
    {
        $encoded = $this->encodeValueForStorage($value);

        return $encoded ?? '';
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

        return $value;
    }

    private function entriesTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bs_fabric_entries';
    }

    private function beginTransaction(): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query('START TRANSACTION');
    }

    private function commitTransaction(): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query('COMMIT');
    }

    private function rollbackTransaction(): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query('ROLLBACK');
    }

    private function entryValuesTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bs_fabric_entry_values';
    }
}
