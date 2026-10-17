<?php

declare(strict_types=1);

namespace BsFabric\Templates;

final class TemplateSettings
{
    public const SHOW_IN_MENU = 'show_in_menu';

    /** Zusätzlich als eigener Punkt im WordPress-Hauptmenü. Default aus. */
    public const SHOW_IN_WP_MENU = 'show_in_wp_menu';

    public const EDITORS_MAY_MANAGE_FIELDS = 'editors_may_manage_fields';

    public const REST_VISIBLE = 'rest_visible';

    /** Öffentliche Frontend-Detailseite unter /{template}/{slug}. Default aus. */
    public const PUBLIC_DETAIL = 'public_detail';

    /**
     * @return array<string, mixed>
     */
    public static function globalDefaults(): array
    {
        return [
            self::SHOW_IN_MENU => true,
            self::SHOW_IN_WP_MENU => false,
            self::EDITORS_MAY_MANAGE_FIELDS => false,
            self::REST_VISIBLE => true,
            self::PUBLIC_DETAIL => false,
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
     * Nur wirksam, wenn das Template auch unter Fabric erscheint (`show_in_menu`).
     *
     * @param array<string, mixed> $settings
     */
    public static function showInWpMenu(array $settings): bool
    {
        return self::showInMenu($settings) && ! empty($settings[self::SHOW_IN_WP_MENU]);
    }

    /**
     * Default: sichtbar — bestehende Templates ohne dieses Setting bleiben unverändert erreichbar (kein Breaking Change).
     *
     * @param array<string, mixed> $settings
     */
    public static function restVisible(array $settings): bool
    {
        return ! isset($settings[self::REST_VISIBLE]) || ! empty($settings[self::REST_VISIBLE]);
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function editorsMayManageFields(array $settings): bool
    {
        return ! empty($settings[self::EDITORS_MAY_MANAGE_FIELDS]);
    }

    /**
     * Default: aus — bestehende Templates bekommen keine öffentlichen URLs ohne Opt-in.
     *
     * @param array<string, mixed> $settings
     */
    public static function publicDetail(array $settings): bool
    {
        return ! empty($settings[self::PUBLIC_DETAIL]);
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
