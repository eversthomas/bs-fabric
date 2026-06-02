<?php

declare(strict_types=1);

namespace BsFabric\Relations;

final class RelationRepository
{
    public const SERVICE_ID = 'relation.repository';

    /**
     * @return list<int>
     */
    public function get(int $fromEntryId, int $fieldId): array
    {
        global $wpdb;

        if ($fromEntryId <= 0 || $fieldId <= 0) {
            return [];
        }

        $table = $this->relationsTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT to_entry_id FROM {$table}
                WHERE from_entry_id = %d AND field_id = %d
                ORDER BY sort_order ASC, id ASC",
                $fromEntryId,
                $fieldId
            )
        );

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $id): int => (int) $id, $rows));
    }

    /**
     * @param list<int> $toEntryIds
     */
    public function set(int $fromEntryId, int $fieldId, array $toEntryIds): bool
    {
        global $wpdb;

        if ($fromEntryId <= 0 || $fieldId <= 0) {
            return false;
        }

        $this->delete($fromEntryId, $fieldId);

        $table = $this->relationsTable();
        $sanitizedIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => absint($id), $toEntryIds),
            static fn (int $id): bool => $id > 0
        )));

        foreach ($sanitizedIds as $index => $toEntryId) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $inserted = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (from_entry_id, to_entry_id, field_id, sort_order) VALUES (%d, %d, %d, %d)",
                    $fromEntryId,
                    $toEntryId,
                    $fieldId,
                    $index
                )
            );

            if ($inserted === false) {
                return false;
            }
        }

        return true;
    }

    public function delete(int $fromEntryId, int $fieldId): bool
    {
        global $wpdb;

        if ($fromEntryId <= 0 || $fieldId <= 0) {
            return false;
        }

        $table = $this->relationsTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE from_entry_id = %d AND field_id = %d",
                $fromEntryId,
                $fieldId
            )
        );

        return $deleted !== false;
    }

    /**
     * @param list<int> $toEntryIds
     */
    public function reorder(int $fromEntryId, int $fieldId, array $toEntryIds): bool
    {
        return $this->set($fromEntryId, $fieldId, $toEntryIds);
    }

    private function relationsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bs_fabric_relations';
    }
}
