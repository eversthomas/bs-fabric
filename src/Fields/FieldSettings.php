<?php

declare(strict_types=1);

namespace BsFabric\Fields;

final class FieldSettings
{
    public const SHOW_IN_LIST = 'show_in_list';

    /**
     * @return array<string, mixed>
     */
    public static function globalDefaults(): array
    {
        return [
            self::SHOW_IN_LIST => false,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function showInList(array $settings): bool
    {
        return ! empty($settings[self::SHOW_IN_LIST]);
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
