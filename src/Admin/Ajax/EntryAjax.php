<?php

declare(strict_types=1);

namespace BsFabric\Admin\Ajax;

use BsFabric\Entries\EntryNotFoundException;
use BsFabric\Entries\EntryRepository;
use BsFabric\Templates\TemplateRepository;

final class EntryAjax
{
    public const NONCE_ACTION = 'bs_fabric_entry_ajax';

    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly TemplateRepository $templateRepository,
    ) {
    }

    public function register(): void
    {
        add_action('wp_ajax_bs_fabric_delete_entry', [$this, 'deleteEntry']);
        add_action('wp_ajax_bs_fabric_search_relation_entries', [$this, 'searchRelationEntries']);
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

    public function searchRelationEntries(): void
    {
        $this->authorize();

        $templateName = isset($_POST['template']) ? sanitize_key((string) wp_unslash($_POST['template'])) : '';
        $search = isset($_POST['search']) ? sanitize_text_field((string) wp_unslash($_POST['search'])) : '';
        $excludeEntryId = isset($_POST['exclude_entry_id']) ? (int) wp_unslash($_POST['exclude_entry_id']) : 0;

        if ($templateName === '') {
            wp_send_json_error(['message' => __('Template fehlt.', 'bs-fabric')], 400);
        }

        $template = $this->findTemplateByName($templateName);

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

    private function findTemplateByName(string $name): ?\BsFabric\Templates\Template
    {
        foreach ($this->templateRepository->getAll() as $template) {
            if ($template->name === $name) {
                return $template;
            }
        }

        return null;
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
