<?php

declare(strict_types=1);

namespace BsFabric\Database;

use wpdb;

final class Installer
{
    public const DB_VERSION = '1.0.2';

    public function __construct(
        private readonly Migrator $migrator,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getTableNames(): array
    {
        global $wpdb;

        return [
            $wpdb->prefix . 'bs_fabric_fields',
            $wpdb->prefix . 'bs_fabric_templates',
            $wpdb->prefix . 'bs_fabric_template_fields',
            $wpdb->prefix . 'bs_fabric_entries',
            $wpdb->prefix . 'bs_fabric_entry_values',
            $wpdb->prefix . 'bs_fabric_relations',
        ];
    }

    public function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();

        dbDelta($this->getFieldsTableSql($charsetCollate));
        dbDelta($this->getTemplatesTableSql($charsetCollate));
        dbDelta($this->getTemplateFieldsTableSql($charsetCollate));
        dbDelta($this->getEntriesTableSql($charsetCollate));
        dbDelta($this->getEntryValuesTableSql($charsetCollate));
        dbDelta($this->getRelationsTableSql($charsetCollate));

        $this->ensureForeignKeys();

        $this->migrator->updateVersion('1.0.0');
    }

    private function getFieldsTableSql(string $charsetCollate): string
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bs_fabric_fields';

        return "CREATE TABLE {$table} (
            id int(10) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            label varchar(200) NOT NULL,
            type varchar(50) NOT NULL,
            settings json NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY name (name)
        ) {$charsetCollate};";
    }

    private function getTemplatesTableSql(string $charsetCollate): string
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bs_fabric_templates';

        return "CREATE TABLE {$table} (
            id int(10) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            label varchar(200) NOT NULL,
            title_field varchar(100) NULL,
            settings json NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY name (name)
        ) {$charsetCollate};";
    }

    private function getTemplateFieldsTableSql(string $charsetCollate): string
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bs_fabric_template_fields';

        return "CREATE TABLE {$table} (
            id int(10) unsigned NOT NULL AUTO_INCREMENT,
            template_id int(10) unsigned NOT NULL,
            field_id int(10) unsigned NOT NULL,
            sort_order int(10) unsigned NOT NULL DEFAULT 0,
            is_required tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_assignment (template_id, field_id),
            KEY template_id (template_id),
            KEY field_id (field_id)
        ) {$charsetCollate};";
    }

    private function getEntriesTableSql(string $charsetCollate): string
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bs_fabric_entries';

        return "CREATE TABLE {$table} (
            id int(10) unsigned NOT NULL AUTO_INCREMENT,
            template_id int(10) unsigned NOT NULL,
            title varchar(500) NULL,
            status enum('draft','published','archived') NOT NULL DEFAULT 'draft',
            language varchar(10) NOT NULL DEFAULT 'de',
            created_by bigint(20) unsigned NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY template_id (template_id),
            KEY idx_entries_template_status (template_id, status),
            KEY idx_entries_created_at (created_at)
        ) {$charsetCollate};";
    }

    private function getEntryValuesTableSql(string $charsetCollate): string
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bs_fabric_entry_values';

        return "CREATE TABLE {$table} (
            id int(10) unsigned NOT NULL AUTO_INCREMENT,
            entry_id int(10) unsigned NOT NULL,
            field_id int(10) unsigned NOT NULL,
            value longtext NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_value (entry_id, field_id),
            KEY entry_id (entry_id),
            KEY field_id (field_id)
        ) {$charsetCollate};";
    }

    private function getRelationsTableSql(string $charsetCollate): string
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bs_fabric_relations';

        return "CREATE TABLE {$table} (
            id int(10) unsigned NOT NULL AUTO_INCREMENT,
            from_entry_id int(10) unsigned NOT NULL,
            to_entry_id int(10) unsigned NOT NULL,
            field_id int(10) unsigned NOT NULL,
            sort_order int(10) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY from_entry_id (from_entry_id),
            KEY to_entry_id (to_entry_id),
            KEY field_id (field_id),
            KEY idx_relations_from_field (from_entry_id, field_id)
        ) {$charsetCollate};";
    }

    private function ensureForeignKeys(): void
    {
        global $wpdb;

        $fields = $wpdb->prefix . 'bs_fabric_fields';
        $templates = $wpdb->prefix . 'bs_fabric_templates';
        $templateFields = $wpdb->prefix . 'bs_fabric_template_fields';
        $entries = $wpdb->prefix . 'bs_fabric_entries';
        $entryValues = $wpdb->prefix . 'bs_fabric_entry_values';
        $relations = $wpdb->prefix . 'bs_fabric_relations';

        $constraints = [
            [
                'table' => $templateFields,
                'name' => 'fk_bs_fabric_tf_template_id',
                'sql' => "ALTER TABLE `{$templateFields}` ADD CONSTRAINT `fk_bs_fabric_tf_template_id` FOREIGN KEY (`template_id`) REFERENCES `{$templates}` (`id`) ON DELETE CASCADE",
            ],
            [
                'table' => $templateFields,
                'name' => 'fk_bs_fabric_tf_field_id',
                'sql' => "ALTER TABLE `{$templateFields}` ADD CONSTRAINT `fk_bs_fabric_tf_field_id` FOREIGN KEY (`field_id`) REFERENCES `{$fields}` (`id`) ON DELETE RESTRICT",
            ],
            [
                'table' => $entries,
                'name' => 'fk_bs_fabric_entries_template_id',
                'sql' => "ALTER TABLE `{$entries}` ADD CONSTRAINT `fk_bs_fabric_entries_template_id` FOREIGN KEY (`template_id`) REFERENCES `{$templates}` (`id`) ON DELETE RESTRICT",
            ],
            [
                'table' => $entryValues,
                'name' => 'fk_bs_fabric_ev_entry_id',
                'sql' => "ALTER TABLE `{$entryValues}` ADD CONSTRAINT `fk_bs_fabric_ev_entry_id` FOREIGN KEY (`entry_id`) REFERENCES `{$entries}` (`id`) ON DELETE CASCADE",
            ],
            [
                'table' => $entryValues,
                'name' => 'fk_bs_fabric_ev_field_id',
                'sql' => "ALTER TABLE `{$entryValues}` ADD CONSTRAINT `fk_bs_fabric_ev_field_id` FOREIGN KEY (`field_id`) REFERENCES `{$fields}` (`id`) ON DELETE RESTRICT",
            ],
            [
                'table' => $relations,
                'name' => 'fk_bs_fabric_rel_from_entry_id',
                'sql' => "ALTER TABLE `{$relations}` ADD CONSTRAINT `fk_bs_fabric_rel_from_entry_id` FOREIGN KEY (`from_entry_id`) REFERENCES `{$entries}` (`id`) ON DELETE CASCADE",
            ],
            [
                'table' => $relations,
                'name' => 'fk_bs_fabric_rel_to_entry_id',
                'sql' => "ALTER TABLE `{$relations}` ADD CONSTRAINT `fk_bs_fabric_rel_to_entry_id` FOREIGN KEY (`to_entry_id`) REFERENCES `{$entries}` (`id`) ON DELETE CASCADE",
            ],
            [
                'table' => $relations,
                'name' => 'fk_bs_fabric_rel_field_id',
                'sql' => "ALTER TABLE `{$relations}` ADD CONSTRAINT `fk_bs_fabric_rel_field_id` FOREIGN KEY (`field_id`) REFERENCES `{$fields}` (`id`) ON DELETE RESTRICT",
            ],
        ];

        foreach ($constraints as $constraint) {
            if ($this->foreignKeyExists($constraint['table'], $constraint['name'])) {
                continue;
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL, Tabellennamen aus $wpdb->prefix.
            $wpdb->query($constraint['sql']);
        }
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*)
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = %s
                    AND TABLE_NAME = %s
                    AND CONSTRAINT_NAME = %s
                    AND CONSTRAINT_TYPE = %s',
                $this->getDatabaseName($wpdb),
                $table,
                $constraintName,
                'FOREIGN KEY'
            )
        );

        return (int) $count > 0;
    }

    private function getDatabaseName(wpdb $wpdb): string
    {
        return is_string($wpdb->dbname) ? $wpdb->dbname : '';
    }
}
