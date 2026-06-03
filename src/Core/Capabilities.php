<?php

declare(strict_types=1);

namespace BsFabric\Core;

final class Capabilities
{
    public const MANAGE_SCHEMA = 'bs_fabric_manage_schema';

    public const MANAGE_ENTRIES = 'bs_fabric_manage_entries';

    public static function register(): void
    {
        $administrator = get_role('administrator');

        if ($administrator !== null) {
            if (! $administrator->has_cap(self::MANAGE_SCHEMA)) {
                $administrator->add_cap(self::MANAGE_SCHEMA);
            }

            if (! $administrator->has_cap(self::MANAGE_ENTRIES)) {
                $administrator->add_cap(self::MANAGE_ENTRIES);
            }
        }

        $editor = get_role('editor');

        if ($editor !== null && ! $editor->has_cap(self::MANAGE_ENTRIES)) {
            $editor->add_cap(self::MANAGE_ENTRIES);
        }
    }
}
