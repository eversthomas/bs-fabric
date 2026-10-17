<?php

declare(strict_types=1);

namespace BsFabric\Database;

final class Migrator
{
    public const OPTION_KEY = 'bs_fabric_db_version';

    public function getVersion(): string
    {
        $version = get_option(self::OPTION_KEY, '0');

        return is_string($version) ? $version : '0';
    }

    public function needsMigration(): bool
    {
        return version_compare($this->getVersion(), Installer::DB_VERSION, '<');
    }

    public function updateVersion(string $version): void
    {
        update_option(self::OPTION_KEY, $version, false);
    }

    public function migrate(Installer $installer): void
    {
        $version = $this->getVersion();

        if (version_compare($version, '1.0.0', '<')) {
            $installer->install();
            $version = '1.0.0';
        }

        if (version_compare($version, '1.0.1', '<')) {
            LegacyDataCleanup::removeLegacyTemplates();
            $this->updateVersion('1.0.1');
            $version = '1.0.1';
        }

        if (version_compare($version, '1.0.2', '<')) {
            $this->addPerformanceIndexes();
            $this->updateVersion('1.0.2');
            $version = '1.0.2';
        }

        if (version_compare($version, '1.0.3', '<')) {
            $this->migrateTo103($installer);
            $this->updateVersion('1.0.3');
            $version = '1.0.3';
        }

        if (version_compare($version, '1.0.4', '<')) {
            $this->migrateTo104();
            $this->updateVersion('1.0.4');
            $version = '1.0.4';
        }

        if (version_compare($version, '1.0.5', '<')) {
            $this->migrateTo105();
            $this->updateVersion('1.0.5');
            $version = '1.0.5';
        }

        if (version_compare($version, '1.0.6', '<')) {
            $this->migrateTo106();
            $this->updateVersion('1.0.6');
        }
    }

    private function migrateTo106(): void
    {
        global $wpdb;

        $entries = $wpdb->prefix . 'bs_fabric_entries';

        if (! $this->columnExists($entries, 'slug')) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL, table from prefix.
            $wpdb->query("ALTER TABLE {$entries} ADD COLUMN slug varchar(200) NULL AFTER title");
        }

        $this->backfillEntrySlugs($entries);

        if (! $this->indexExists($entries, 'unique_template_slug')) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL, table from prefix.
            $wpdb->query(
                "ALTER TABLE {$entries} ADD UNIQUE KEY unique_template_slug (template_id, slug)"
            );
        }
    }

    private function backfillEntrySlugs(string $entriesTable): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from prefix.
        $rows = $wpdb->get_results(
            "SELECT id, template_id, title, slug FROM {$entriesTable}",
            ARRAY_A
        );

        if (! is_array($rows)) {
            return;
        }

        /** @var array<int, array<string, true>> $usedByTemplate */
        $usedByTemplate = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $templateId = (int) ($row['template_id'] ?? 0);
            $existing = isset($row['slug']) && is_string($row['slug']) ? $row['slug'] : '';

            if ($templateId > 0 && $existing !== '') {
                $usedByTemplate[$templateId][$existing] = true;
            }
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $entryId = (int) ($row['id'] ?? 0);
            $templateId = (int) ($row['template_id'] ?? 0);
            $existing = isset($row['slug']) && is_string($row['slug']) ? $row['slug'] : '';

            if ($entryId <= 0 || $templateId <= 0 || $existing !== '') {
                continue;
            }

            $title = isset($row['title']) && is_string($row['title']) ? $row['title'] : '';
            $base = sanitize_title($title !== '' ? $title : 'entry-' . $entryId);

            if ($base === '') {
                $base = 'entry-' . $entryId;
            }

            $slug = $base;
            $suffix = 2;

            while (isset($usedByTemplate[$templateId][$slug])) {
                $slug = $base . '-' . $suffix;
                ++$suffix;
            }

            $usedByTemplate[$templateId][$slug] = true;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from prefix.
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$entriesTable} SET slug = %s WHERE id = %d",
                    $slug,
                    $entryId
                )
            );
        }
    }

    private function migrateTo105(): void
    {
        global $wpdb;

        $entries = $wpdb->prefix . 'bs_fabric_entries';

        if (! $this->columnExists($entries, 'editor_note')) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL, table from prefix.
            $wpdb->query("ALTER TABLE {$entries} ADD COLUMN editor_note longtext NULL AFTER updated_by");
        }
    }

    private function migrateTo104(): void
    {
        global $wpdb;

        $templateFields = $wpdb->prefix . 'bs_fabric_template_fields';

        if (! $this->columnExists($templateFields, 'rest_hidden')) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL, table from prefix.
            $wpdb->query("ALTER TABLE {$templateFields} ADD COLUMN rest_hidden tinyint(1) NOT NULL DEFAULT 0 AFTER is_required");
        }
    }

    private function migrateTo103(Installer $installer): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $entries = $wpdb->prefix . 'bs_fabric_entries';

        if (! $this->columnExists($entries, 'updated_by')) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL, table from prefix.
            $wpdb->query("ALTER TABLE {$entries} ADD COLUMN updated_by bigint(20) unsigned NULL AFTER created_by");
        }

        dbDelta($installer->getEntryLogTableSqlForMigration($wpdb->get_charset_collate()));
    }

    private function columnExists(string $table, string $column): bool
    {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = %s
                    AND TABLE_NAME = %s
                    AND COLUMN_NAME = %s',
                $this->getDatabaseName($wpdb),
                $table,
                $column
            )
        );

        return (int) $count > 0;
    }

    private function addPerformanceIndexes(): void
    {
        global $wpdb;

        $entries = $wpdb->prefix . 'bs_fabric_entries';
        $relations = $wpdb->prefix . 'bs_fabric_relations';

        $this->addIndexIfMissing($entries, 'idx_entries_template_status', '(template_id, status)');
        $this->addIndexIfMissing($entries, 'idx_entries_created_at', '(created_at)');
        $this->addIndexIfMissing($relations, 'idx_relations_from_field', '(from_entry_id, field_id)');
    }

    private function addIndexIfMissing(string $table, string $indexName, string $columns): void
    {
        global $wpdb;

        if ($this->indexExists($table, $indexName)) {
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL, Tabellenname aus $wpdb->prefix.
        $wpdb->query("CREATE INDEX {$indexName} ON {$table} {$columns}");
    }

    private function indexExists(string $table, string $indexName): bool
    {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*)
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = %s
                    AND TABLE_NAME = %s
                    AND INDEX_NAME = %s',
                $this->getDatabaseName($wpdb),
                $table,
                $indexName
            )
        );

        return (int) $count > 0;
    }

    private function getDatabaseName(\wpdb $wpdb): string
    {
        return is_string($wpdb->dbname) ? $wpdb->dbname : '';
    }
}
