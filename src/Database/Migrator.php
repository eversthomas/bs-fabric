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
        }
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
