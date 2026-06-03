<?php

declare(strict_types=1);

/**
 * Runs only when the plugin is deleted via WordPress admin — not on deactivate.
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$settings = get_option('bs_fabric_plugin_settings', []);
$deleteData = is_array($settings) && ! empty($settings['delete_on_uninstall']);

if ($deleteData) {
    $tables = [
        $wpdb->prefix . 'bs_fabric_relations',
        $wpdb->prefix . 'bs_fabric_entry_values',
        $wpdb->prefix . 'bs_fabric_entries',
        $wpdb->prefix . 'bs_fabric_template_fields',
        $wpdb->prefix . 'bs_fabric_templates',
        $wpdb->prefix . 'bs_fabric_fields',
    ];

    foreach ($tables as $table) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
    }
}

delete_option('bs_fabric_db_version');
delete_option('bs_fabric_plugin_settings');

foreach (['administrator', 'editor'] as $roleName) {
    $role = get_role($roleName);

    if ($role === null) {
        continue;
    }

    $role->remove_cap('bs_fabric_manage_schema');
    $role->remove_cap('bs_fabric_manage_entries');
}
