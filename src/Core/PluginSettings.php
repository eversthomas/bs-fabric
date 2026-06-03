<?php

declare(strict_types=1);

namespace BsFabric\Core;

final class PluginSettings
{
    public const OPTION_KEY = 'bs_fabric_plugin_settings';

    public const MENU_LABEL = 'menu_label';

    public const DEFAULT_ENTRY_STATUS = 'default_entry_status';

    public const LOCK_FIELD_KEYS = 'lock_field_keys';

    public const DELETE_ON_UNINSTALL = 'delete_on_uninstall';

    /** @var list<string> */
    public const ENTRY_STATUSES = ['draft', 'published', 'archived'];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            self::MENU_LABEL => 'Fabric',
            self::DEFAULT_ENTRY_STATUS => 'draft',
            self::LOCK_FIELD_KEYS => true,
            self::DELETE_ON_UNINSTALL => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        $stored = get_option(self::OPTION_KEY, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        return array_merge(self::defaults(), $stored);
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function save(array $input): void
    {
        $settings = self::sanitize($input);
        update_option(self::OPTION_KEY, $settings, false);
    }

    public static function menuLabel(): string
    {
        $label = trim((string) (self::get()[self::MENU_LABEL] ?? ''));

        return $label !== '' ? $label : (string) self::defaults()[self::MENU_LABEL];
    }

    public static function defaultEntryStatus(): string
    {
        $status = sanitize_key((string) (self::get()[self::DEFAULT_ENTRY_STATUS] ?? 'draft'));

        return in_array($status, self::ENTRY_STATUSES, true) ? $status : 'draft';
    }

    public static function lockFieldKeys(): bool
    {
        return ! empty(self::get()[self::LOCK_FIELD_KEYS]);
    }

    public static function deleteOnUninstall(): bool
    {
        return ! empty(self::get()[self::DELETE_ON_UNINSTALL]);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private static function sanitize(array $input): array
    {
        $menuLabel = isset($input[self::MENU_LABEL])
            ? sanitize_text_field((string) $input[self::MENU_LABEL])
            : (string) self::defaults()[self::MENU_LABEL];

        if ($menuLabel === '') {
            $menuLabel = (string) self::defaults()[self::MENU_LABEL];
        }

        $menuLabel = mb_substr($menuLabel, 0, 50);

        $status = isset($input[self::DEFAULT_ENTRY_STATUS])
            ? sanitize_key((string) $input[self::DEFAULT_ENTRY_STATUS])
            : 'draft';

        if (! in_array($status, self::ENTRY_STATUSES, true)) {
            $status = 'draft';
        }

        return [
            self::MENU_LABEL => $menuLabel,
            self::DEFAULT_ENTRY_STATUS => $status,
            self::LOCK_FIELD_KEYS => ! empty($input[self::LOCK_FIELD_KEYS]),
            self::DELETE_ON_UNINSTALL => ! empty($input[self::DELETE_ON_UNINSTALL]),
        ];
    }
}
