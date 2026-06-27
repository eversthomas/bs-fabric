<?php

declare(strict_types=1);

namespace BsFabric\Admin\Ajax;

use BsFabric\Admin\ExplorerEntryFormatter;
use BsFabric\Entries\EntryAdminOperations;
use BsFabric\Entries\EntryNotFoundException;
use BsFabric\Templates\TemplateLookup;

final class EntryAjax
{
    public const NONCE_ACTION = 'bs_fabric_entry_ajax';

    public function __construct(
        private readonly EntryAdminOperations $entryRepository,
        private readonly TemplateLookup $templateRepository,
    ) {
    }

    public function register(): void
    {
        add_action('wp_ajax_bs_fabric_delete_entry', [$this, 'deleteEntry']);
        add_action('wp_ajax_bs_fabric_search_relation_entries', [$this, 'searchRelationEntries']);
        add_action('wp_ajax_bs_fabric_explorer_rows', [$this, 'fetchExplorerRows']);
        add_action('wp_ajax_bs_fabric_bulk_entries', [$this, 'bulkEntries']);
    }

    public function deleteEntry(): void
    {
        $this->authorize();

        $entryId = isset($_POST['entry_id']) ? (int) wp_unslash($_POST['entry_id']) : 0;

        if ($entryId <= 0) {
            wp_send_json_error(['message' => __('Ungültige Entry-ID.', 'bs-fabric')], 400);
        }

        try {
            $this->entryRepository->delete($entryId);
        } catch (EntryNotFoundException) {
            wp_send_json_error(['message' => __('Entry nicht gefunden.', 'bs-fabric')], 404);
        }

        wp_send_json_success(['message' => __('Entry wurde gelöscht.', 'bs-fabric')]);
    }

    public function bulkEntries(): void
    {
        $this->authorize();

        $templateId = isset($_POST['template_id']) ? (int) wp_unslash($_POST['template_id']) : 0;
        $action = isset($_POST['bulk_action']) ? sanitize_key((string) wp_unslash($_POST['bulk_action'])) : '';
        $entryIds = isset($_POST['entry_ids']) ? wp_unslash($_POST['entry_ids']) : [];

        if ($templateId <= 0) {
            wp_send_json_error(['message' => __('Ungültige Template-ID.', 'bs-fabric')], 400);
        }

        if (! is_array($entryIds) || $entryIds === []) {
            wp_send_json_error(['message' => __('Keine Einträge ausgewählt.', 'bs-fabric')], 400);
        }

        $entryIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $entryIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($entryIds === []) {
            wp_send_json_error(['message' => __('Keine Einträge ausgewählt.', 'bs-fabric')], 400);
        }

        $processed = 0;
        $createdIds = [];

        // TODO Phase 2: per-Entry capability checks if granular template caps are introduced.
        foreach ($entryIds as $entryId) {
            if ($this->entryRepository->getTemplateIdForEntry($entryId) !== $templateId) {
                continue;
            }

            try {
                $processed += match ($action) {
                    'publish' => $this->entryRepository->updateStatus($entryId, 'published') ? 1 : 0,
                    'delete' => $this->entryRepository->delete($entryId) ? 1 : 0,
                    'duplicate' => $this->processDuplicate($entryId, $createdIds),
                    default => 0,
                };
            } catch (EntryNotFoundException) {
                continue;
            }
        }

        if ($action === 'duplicate') {
            wp_send_json_success([
                'message' => sprintf(
                    /* translators: %d: number of duplicated entries */
                    _n('%d Eintrag dupliziert.', '%d Einträge dupliziert.', count($createdIds), 'bs-fabric'),
                    count($createdIds)
                ),
                'created_ids' => $createdIds,
            ]);
        }

        if ($processed === 0) {
            wp_send_json_error(['message' => __('Bulk-Aktion fehlgeschlagen.', 'bs-fabric')], 400);
        }

        $message = match ($action) {
            'publish' => sprintf(
                /* translators: %d: number of published entries */
                _n('%d Eintrag veröffentlicht.', '%d Einträge veröffentlicht.', $processed, 'bs-fabric'),
                $processed
            ),
            'delete' => sprintf(
                /* translators: %d: number of deleted entries */
                _n('%d Eintrag gelöscht.', '%d Einträge gelöscht.', $processed, 'bs-fabric'),
                $processed
            ),
            default => __('Bulk-Aktion abgeschlossen.', 'bs-fabric'),
        };

        wp_send_json_success(['message' => $message, 'processed' => $processed]);
    }

    /**
     * @param list<int> $createdIds
     */
    private function processDuplicate(int $entryId, array &$createdIds): int
    {
        $newId = $this->entryRepository->duplicate($entryId);
        $createdIds[] = $newId;

        return 1;
    }

    public function fetchExplorerRows(): void
    {
        $this->authorize();

        $filters = $this->parseExplorerFilters();

        $result = $this->entryRepository->queryExplorerRows([
            'page' => isset($_POST['page']) ? (int) wp_unslash($_POST['page']) : 1,
            'per_page' => isset($_POST['per_page']) ? (int) wp_unslash($_POST['per_page']) : 20,
            'search' => isset($_POST['search']) ? (string) wp_unslash($_POST['search']) : '',
            'template_id' => $filters['template_id'],
            'status' => $filters['status'],
            'sort_field' => isset($_POST['sort_field']) ? (string) wp_unslash($_POST['sort_field']) : 'created_at',
            'sort_direction' => isset($_POST['sort_direction']) ? (string) wp_unslash($_POST['sort_direction']) : 'desc',
        ]);

        wp_send_json_success([
            'entries' => ExplorerEntryFormatter::formatRows($result['rows']),
            'pagination' => [
                'totalItems' => $result['total'],
                'totalPages' => $result['total_pages'],
            ],
        ]);
    }

    public function searchRelationEntries(): void
    {
        $this->authorize();

        $templateName = isset($_POST['template']) ? sanitize_key((string) wp_unslash($_POST['template'])) : '';
        $search = isset($_POST['search']) ? sanitize_text_field((string) wp_unslash($_POST['search'])) : '';
        $excludeEntryId = isset($_POST['exclude_entry_id']) ? (int) wp_unslash($_POST['exclude_entry_id']) : 0;

        if ($templateName === '') {
            wp_send_json_error(['message' => __('Template fehlt.', 'bs-fabric')], 400);
        }

        $template = $this->templateRepository->getByName($templateName);

        if ($template === null) {
            wp_send_json_success(['results' => []]);
        }

        $results = $this->searchEntries($template->getId(), $search, $excludeEntryId);

        wp_send_json_success(['results' => $results]);
    }

    private function authorize(): void
    {
        if (! current_user_can('bs_fabric_manage_entries')) {
            wp_send_json_error(['message' => __('Keine Berechtigung.', 'bs-fabric')], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');
    }

    /**
     * @return array{template_id: int, status: string}
     */
    private function parseExplorerFilters(): array
    {
        $templateId = 0;
        $status = '';

        if (! isset($_POST['filters']) || ! is_string($_POST['filters'])) {
            return [
                'template_id' => $templateId,
                'status' => $status,
            ];
        }

        $decoded = json_decode(wp_unslash($_POST['filters']), true);

        if (! is_array($decoded)) {
            return [
                'template_id' => $templateId,
                'status' => $status,
            ];
        }

        foreach ($decoded as $filter) {
            if (! is_array($filter)) {
                continue;
            }

            $field = sanitize_key((string) ($filter['field'] ?? ''));
            $operator = sanitize_key((string) ($filter['operator'] ?? ''));
            $value = $filter['value'] ?? null;

            if ($operator !== 'is' && $operator !== 'isAny') {
                continue;
            }

            // TODO Phase 2: isAny mit OR-Logik für mehrere Werte unterstützen
            if ($field === 'status' && $operator === 'isAny' && is_array($value)) {
                // DataViews sendet bei isAny ein Array — wir nehmen den ersten Wert
                // für den einfachen Filter; Phase 2 wird OR-Logik einführen
                $first = reset($value);

                if (is_scalar($first)) {
                    $status = sanitize_key((string) $first);
                }
            }

            if ($field === 'template_id' && $operator === 'isAny' && is_array($value)) {
                $first = reset($value);

                if (is_scalar($first)) {
                    $templateId = (int) $first;
                }
            }

            if ($field === 'template_id' && is_scalar($value)) {
                $templateId = (int) $value;
            }

            if ($field === 'status' && is_scalar($value)) {
                $status = sanitize_key((string) $value);
            }
        }

        return [
            'template_id' => $templateId,
            'status' => $status,
        ];
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function searchEntries(int $templateId, string $search, int $excludeEntryId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bs_fabric_entries';
        $like = '%' . $wpdb->esc_like($search) . '%';

        if ($search !== '') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, title FROM {$table}
                    WHERE template_id = %d
                        AND id != %d
                        AND (title LIKE %s OR CAST(id AS CHAR) LIKE %s)
                    ORDER BY title ASC
                    LIMIT 20",
                    $templateId,
                    $excludeEntryId,
                    $like,
                    $like
                ),
                ARRAY_A
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, title FROM {$table}
                    WHERE template_id = %d AND id != %d
                    ORDER BY title ASC
                    LIMIT 20",
                    $templateId,
                    $excludeEntryId
                ),
                ARRAY_A
            );
        }

        if (! is_array($rows)) {
            return [];
        }

        $results = [];

        foreach ($rows as $row) {
            $title = isset($row['title']) && is_string($row['title']) && $row['title'] !== ''
                ? $row['title']
                : sprintf('#%d', (int) $row['id']);

            $results[] = [
                'id' => (int) $row['id'],
                'title' => $title,
            ];
        }

        return $results;
    }
}
