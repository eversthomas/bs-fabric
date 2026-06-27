<?php

declare(strict_types=1);

namespace BsFabric\Admin\Components;

final class Icon
{
    /**
     * @var array<string, array{paths: string, fill?: bool, vb?: int, sw?: float}>
     */
    private const ICONS = [
        'dash' => [
            'paths' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        ],
        'layers' => [
            'paths' => '<path d="M12 3l8 4.5-8 4.5-8-4.5L12 3z"/><path d="M4 12l8 4.5 8-4.5"/><path d="M4 16.5l8 4.5 8-4.5"/>',
        ],
        'threads' => [
            'paths' => '<path d="M4 6h10"/><circle cx="18" cy="6" r="2"/><path d="M4 12h6"/><circle cx="14" cy="12" r="2"/><path d="M4 18h12"/><circle cx="20" cy="18" r="2"/>',
        ],
        'rows' => [
            'paths' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9.5h18M3 15h18M9 4v16"/>',
        ],
        'plus' => [
            'paths' => '<path d="M12 5v14M5 12h14"/>',
        ],
        'chevr' => [
            'paths' => '<path d="M9 6l6 6-6 6"/>',
        ],
        'check' => [
            'paths' => '<path d="M5 12.5l4.5 4.5L19 7"/>',
        ],
        'x' => [
            'paths' => '<path d="M6 6l12 12M18 6L6 18"/>',
        ],
        'edit' => [
            'paths' => '<path d="M4 20h4l10-10a2.8 2.8 0 00-4-4L4 16v4z"/><path d="M13.5 6.5l4 4"/>',
        ],
        'box' => [
            'paths' => '<path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z"/><path d="M4 7.5l8 4.5 8-4.5M12 12v9"/>',
        ],
        'info' => [
            'paths' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        ],
        'search' => [
            'paths' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/>',
        ],
        'chevd' => [
            'paths' => '<path d="M6 9l6 6 6-6"/>',
        ],
        'chevl' => [
            'paths' => '<path d="M15 6l-6 6 6 6"/>',
        ],
        'trash' => [
            'paths' => '<path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2M6 7l1 13a1 1 0 001 1h8a1 1 0 001-1l1-13"/>',
        ],
        'drag' => [
            'paths' => '<circle cx="9" cy="6" r="1.4" fill="currentColor" stroke="none"/><circle cx="9" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="9" cy="18" r="1.4" fill="currentColor" stroke="none"/><circle cx="15" cy="6" r="1.4" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="15" cy="18" r="1.4" fill="currentColor" stroke="none"/>',
            'fill' => true,
        ],
        'save' => [
            'paths' => '<path d="M5 3h11l3 3v15H5V3z"/><path d="M8 3v5h7M8 21v-7h8v7"/>',
        ],
        'bolt' => [
            'paths' => '<path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"/>',
        ],
        'filter' => [
            'paths' => '<path d="M3 5h18l-7 8v6l-4-2v-4L3 5z"/>',
        ],
        'list' => [
            'paths' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        ],
        'grid' => [
            'paths' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        ],
        'copy' => [
            'paths' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>',
        ],
        't_text' => [
            'paths' => '<path d="M5 7V5h14v2M12 5v14M9 19h6"/>',
        ],
        't_textarea' => [
            'paths' => '<rect x="4" y="5" width="16" height="14" rx="2"/><path d="M7 9h10M7 12h10M7 15h6"/>',
        ],
        't_richtext' => [
            'paths' => '<path d="M5 6h6M5 6v12M8 6v12M14 9h5M14 13h5M14 17h3"/><path d="M5 18h6"/>',
        ],
        't_select' => [
            'paths' => '<path d="M8 9l4 4 4-4M6 5h12a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2z"/>',
        ],
        't_boolean' => [
            'paths' => '<rect x="3" y="8" width="18" height="8" rx="4"/><circle cx="9" cy="12" r="3" fill="currentColor" stroke="none"/>',
            'fill' => true,
        ],
        't_number' => [
            'paths' => '<path d="M8 7h8M8 12h8M8 17h5"/><path d="M5 5v14"/>',
        ],
        't_date' => [
            'paths' => '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 9h16M9 3v4M15 3v4"/>',
        ],
        't_media' => [
            'paths' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.6"/><path d="M21 16l-5-5-8 8"/>',
        ],
        't_relation' => [
            'paths' => '<path d="M9 12a3 3 0 013-3h2M15 12a3 3 0 01-3 3h-2"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="12" r="3"/>',
        ],
        't_address' => [
            'paths' => '<path d="M12 21s7-5.4 7-11a7 7 0 10-14 0c0 5.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        ],
        't_code' => [
            'paths' => '<path d="M8 8l-4 4 4 4M16 8l4 4-4 4M14 4l-4 16"/>',
        ],
    ];

    public static function render(string $name, int $size = 18, bool $fill = false): string
    {
        $icon = self::ICONS[$name] ?? self::ICONS['info'];
        $viewBox = (int) ($icon['vb'] ?? 24);
        $strokeWidth = (string) ($icon['sw'] ?? 1.8);
        $useFill = $fill || ($icon['fill'] ?? false);
        $paths = $icon['paths'];

        if ($useFill) {
            $svg = sprintf(
                '<svg class="bsf-icon" viewBox="0 0 %1$d %1$d" width="%2$d" height="%2$d" fill="currentColor" aria-hidden="true">%3$s</svg>',
                $viewBox,
                $size,
                $paths
            );
        } else {
            $svg = sprintf(
                '<svg class="bsf-icon" viewBox="0 0 %1$d %1$d" width="%2$d" height="%2$d" fill="none" stroke="currentColor" stroke-width="%3$s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%4$s</svg>',
                $viewBox,
                $size,
                esc_attr($strokeWidth),
                $paths
            );
        }

        return $svg;
    }
}
