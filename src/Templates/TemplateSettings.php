<?php

declare(strict_types=1);

namespace BsFabric\Templates;

final class TemplateSettings
{
    public const SHOW_IN_MENU = 'show_in_menu';

    /**
     * @return array<string, mixed>
     */
    public static function globalDefaults(): array
    {
        return [
            self::SHOW_IN_MENU => true,
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
     *
     * @return array<string, mixed>
     */
    public static function mergeGlobal(array $settings): array
    {
        return array_merge(self::globalDefaults(), $settings);
    }
}
