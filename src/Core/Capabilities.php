<?php

declare(strict_types=1);

namespace BsFabric\Core;

final class Capabilities
{
    public const MANAGE_SCHEMA = 'bs_fabric_manage_schema';

    public const MANAGE_ENTRIES = 'bs_fabric_manage_entries';

    /** Felder aus der Bibliothek einem Template zuweisen (wenn Admin es pro Template erlaubt). */
    public const ASSIGN_TEMPLATE_FIELDS = 'bs_fabric_assign_template_fields';

    public const ROLE_MITARBEITER = 'bs_fabric_mitarbeiter';

    private static bool $filtersRegistered = false;

    public static function register(): void
    {
        self::registerAdministratorCaps();
        self::registerEditorCaps();
        self::registerMitarbeiterRole();
        self::registerFilters();
    }

    /**
     * @param array<int, string> $caps
     * @param array<int, mixed> $args
     *
     * @return array<int, string>
     */
    public static function mapMetaCap(array $caps, string $cap, int $userId, array $args): array
    {
        if (! in_array($cap, [self::MANAGE_SCHEMA, self::MANAGE_ENTRIES, self::ASSIGN_TEMPLATE_FIELDS], true)) {
            return $caps;
        }

        return [$cap];
    }

    /**
     * Stellt sicher, dass die Fabric-Mitarbeiter-Rolle im Admin zuverlässig auf Listen zugreifen kann.
     *
     * @param array<string, bool> $allcaps
     * @param array<int, string> $caps
     * @param array<int, mixed> $args
     * @param object{roles?: list<string>} $user
     *
     * @return array<string, bool>
     */
    public static function grantRoleCapabilities(array $allcaps, array $caps, array $args, object $user): array
    {
        if (! in_array(self::ROLE_MITARBEITER, (array) $user->roles, true)) {
            return $allcaps;
        }

        $allcaps['read'] = true;
        $allcaps[self::MANAGE_ENTRIES] = true;
        unset($allcaps[self::MANAGE_SCHEMA], $allcaps[self::ASSIGN_TEMPLATE_FIELDS]);

        return $allcaps;
    }

    private static function registerFilters(): void
    {
        if (self::$filtersRegistered) {
            return;
        }

        add_filter('map_meta_cap', [self::class, 'mapMetaCap'], 10, 4);
        add_filter('user_has_cap', [self::class, 'grantRoleCapabilities'], 10, 4);
        self::$filtersRegistered = true;
    }

    private static function registerAdministratorCaps(): void
    {
        $administrator = get_role('administrator');

        if ($administrator === null) {
            return;
        }

        foreach ([self::MANAGE_SCHEMA, self::MANAGE_ENTRIES, self::ASSIGN_TEMPLATE_FIELDS] as $cap) {
            if (! $administrator->has_cap($cap)) {
                $administrator->add_cap($cap);
            }
        }
    }

    private static function registerEditorCaps(): void
    {
        $editor = get_role('editor');

        if ($editor === null) {
            return;
        }

        foreach ([self::MANAGE_ENTRIES, self::ASSIGN_TEMPLATE_FIELDS] as $cap) {
            if (! $editor->has_cap($cap)) {
                $editor->add_cap($cap);
            }
        }
    }

    private static function registerMitarbeiterRole(): void
    {
        $displayName = __('Fabric Mitarbeiter', 'bs-fabric');

        if (get_role(self::ROLE_MITARBEITER) === null) {
            add_role(
                self::ROLE_MITARBEITER,
                $displayName,
                [
                    'read' => true,
                    self::MANAGE_ENTRIES => true,
                ]
            );
        }

        $role = get_role(self::ROLE_MITARBEITER);

        if ($role === null) {
            return;
        }

        if (! $role->has_cap('read')) {
            $role->add_cap('read');
        }

        if (! $role->has_cap(self::MANAGE_ENTRIES)) {
            $role->add_cap(self::MANAGE_ENTRIES);
        }

        if ($role->has_cap(self::ASSIGN_TEMPLATE_FIELDS)) {
            $role->remove_cap(self::ASSIGN_TEMPLATE_FIELDS);
        }

        if ($role->has_cap(self::MANAGE_SCHEMA)) {
            $role->remove_cap(self::MANAGE_SCHEMA);
        }

        self::syncMitarbeiterRoleDisplayName($displayName);
    }

    private static function syncMitarbeiterRoleDisplayName(string $displayName): void
    {
        global $wp_roles;

        if (! $wp_roles instanceof \WP_Roles) {
            return;
        }

        if (! isset($wp_roles->roles[self::ROLE_MITARBEITER])) {
            return;
        }

        if (($wp_roles->roles[self::ROLE_MITARBEITER]['name'] ?? '') === $displayName) {
            return;
        }

        $wp_roles->roles[self::ROLE_MITARBEITER]['name'] = $displayName;
        update_option($wp_roles->role_key, $wp_roles->roles);
    }
}
