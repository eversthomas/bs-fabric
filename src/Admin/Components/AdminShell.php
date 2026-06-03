<?php

declare(strict_types=1);

namespace BsFabric\Admin\Components;

final class AdminShell
{
    public const WRAP_CLASS = 'bs-fabric-wrap';

    public static function registerHooks(): void
    {
        add_filter('admin_body_class', [self::class, 'filterBodyClass']);
    }

    public static function filterBodyClass(string $classes): string
    {
        if (! self::isFabricAdminPage()) {
            return $classes;
        }

        return $classes . ' bs-fabric-admin';
    }

    public static function isFabricAdminPage(): bool
    {
        if (! is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

        if ($page === '') {
            return false;
        }

        return str_starts_with($page, 'bs-fabric');
    }

    /**
     * @param list<array{label: string, url?: string}> $crumbs
     */
    public static function open(array $crumbs = []): void
    {
        echo '<div class="wrap ' . esc_attr(self::WRAP_CLASS) . '">';
        echo '<div class="bsf-app">';
        echo '<div class="bsf-main">';
        self::renderTopbar($crumbs);
        echo '<div class="bsf-content"><div class="bsf-page route-fade">';
    }

    public static function close(): void
    {
        echo '</div></div>';
        echo '<div class="bsf-toast-wrap" id="bsf-toast-root" aria-live="polite"></div>';
        echo '</div></div></div>';
    }

    /**
     * @param list<array{label: string, url?: string}> $crumbs
     */
    public static function renderTopbar(array $crumbs): void
    {
        echo '<header class="bsf-topbar">';
        echo '<nav class="bsf-crumbs" aria-label="' . esc_attr__('Brotkrumen', 'bs-fabric') . '">';

        foreach ($crumbs as $index => $crumb) {
            if ($index > 0) {
                echo '<span class="bsf-crumb-sep">' . Icon::render('chevr', 13) . '</span>';
            }

            $label = $crumb['label'];
            $url = $crumb['url'] ?? null;

            if ($url !== null && $url !== '') {
                printf(
                    '<a href="%s">%s</a>',
                    esc_url($url),
                    esc_html($label)
                );
            } else {
                echo '<span class="bsf-crumb-here">' . esc_html($label) . '</span>';
            }
        }

        echo '</nav>';
        echo '<div class="bsf-topbar-spacer"></div>';
        echo '</header>';
    }

    public static function enqueueAssets(): void
    {
        wp_enqueue_style(
            'bs-fabric-bsf-tokens',
            BS_FABRIC_URL . 'assets/css/bsf-tokens.css',
            [],
            BS_FABRIC_VERSION
        );

        wp_enqueue_style(
            'bs-fabric-bsf-components',
            BS_FABRIC_URL . 'assets/css/bsf-components.css',
            ['bs-fabric-bsf-tokens'],
            BS_FABRIC_VERSION
        );

        wp_enqueue_style(
            'bs-fabric-bsf-wp-bridge',
            BS_FABRIC_URL . 'assets/css/bsf-wp-bridge.css',
            ['bs-fabric-bsf-components'],
            BS_FABRIC_VERSION
        );

        wp_enqueue_script(
            'bs-fabric-bsf-ui',
            BS_FABRIC_URL . 'assets/js/bsf-ui.js',
            [],
            BS_FABRIC_VERSION,
            true
        );
    }
}
