<?php

declare(strict_types=1);

namespace BsFabric\Database;

final class LegacyDataCleanup
{
    /** @var list<string> */
    public const LEGACY_TEMPLATE_NAMES = [
        'unique_template',
    ];

    public static function removeLegacyTemplates(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bs_fabric_templates';

        foreach (self::LEGACY_TEMPLATE_NAMES as $name) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE name = %s",
                    $name
                )
            );
        }
    }
}
