<?php

declare(strict_types=1);

namespace BsFabric\Entries;

final class EntryAuditRepository
{
    public const SERVICE_ID = 'entry.audit';

    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    public function log(
        int $entryId,
        int $templateId,
        string $action,
        ?string $entryTitle = null,
        ?int $userId = null,
    ): void {
        global $wpdb;

        if ($entryId <= 0 || $templateId <= 0) {
            return;
        }

        if (! in_array($action, [self::ACTION_CREATED, self::ACTION_UPDATED, self::ACTION_DELETED], true)) {
            return;
        }

        $userId = $userId ?? get_current_user_id();

        if ($userId <= 0) {
            return;
        }

        $table = $this->tableName();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (entry_id, template_id, action, user_id, entry_title)
                VALUES (%d, %d, %s, %d, %s)",
                $entryId,
                $templateId,
                $action,
                $userId,
                $entryTitle !== null && $entryTitle !== '' ? $entryTitle : null
            )
        );
    }

    /**
     * @return list<array{
     *     id: int,
     *     entry_id: int,
     *     template_id: int,
     *     action: string,
     *     user_id: int,
     *     user_name: string,
     *     entry_title: string|null,
     *     created_at: string
     * }>
     */
    public function getForEntry(int $entryId, int $limit = 20): array
    {
        global $wpdb;

        if ($entryId <= 0) {
            return [];
        }

        $limit = max(1, min($limit, 100));
        $table = $this->tableName();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- LIMIT bound as int.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, entry_id, template_id, action, user_id, entry_title, created_at
                FROM {$table}
                WHERE entry_id = %d
                ORDER BY id DESC
                LIMIT {$limit}",
                $entryId
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        $entries = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $userId = isset($row['user_id']) ? (int) $row['user_id'] : 0;
            $userName = $userId > 0
                ? (string) get_the_author_meta('display_name', $userId)
                : __('Unbekannt', 'bs-fabric');

            $title = isset($row['entry_title']) && is_string($row['entry_title']) && $row['entry_title'] !== ''
                ? $row['entry_title']
                : null;

            $entries[] = [
                'id' => (int) ($row['id'] ?? 0),
                'entry_id' => (int) ($row['entry_id'] ?? 0),
                'template_id' => (int) ($row['template_id'] ?? 0),
                'action' => (string) ($row['action'] ?? ''),
                'user_id' => $userId,
                'user_name' => $userName !== '' ? $userName : __('Unbekannt', 'bs-fabric'),
                'entry_title' => $title,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $entries;
    }

    public function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bs_fabric_entry_log';
    }
}
