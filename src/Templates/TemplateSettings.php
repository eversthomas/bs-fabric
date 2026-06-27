<?php

declare(strict_types=1);

namespace BsFabric\Templates;

final class TemplateSettings
{
    public const SHOW_IN_MENU = 'show_in_menu';

    public const EDITORS_MAY_MANAGE_FIELDS = 'editors_may_manage_fields';

    /**
     * @return array<string, mixed>
     */
    public static function globalDefaults(): array
    {
        return [
            self::SHOW_IN_MENU => true,
            self::EDITORS_MAY_MANAGE_FIELDS => false,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function showInMenu(array $settings): bool
    {
        return ! isset($settings[self::SHOW_IN_MENU]) || ! empty($settings[self::SHOW_IN_MENU]);
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function editorsMayManageFields(array $settings): bool
    {
        return ! empty($settings[self::EDITORS_MAY_MANAGE_FIELDS]);
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    public static function mergeGlobal(array $settings): array
    {
        return array_merge(self::globalDefaults(), $settings);
    }
}
