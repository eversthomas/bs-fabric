<?php

declare(strict_types=1);

namespace BsFabric\Admin\Components;

final class StatusBadge
{
    public static function render(string $status): string
    {
        $map = [
            'published' => ['label' => __('Veröffentlicht', 'bs-fabric'), 'class' => 'bsf-badge-ok'],
            'draft' => ['label' => __('Entwurf', 'bs-fabric'), 'class' => 'bsf-badge-muted'],
            'archived' => ['label' => __('Archiviert', 'bs-fabric'), 'class' => 'bsf-badge-warn'],
        ];

        $entry = $map[$status] ?? ['label' => $status, 'class' => 'bsf-badge-muted'];

        return sprintf(
            '<span class="bsf-badge bsf-badge-dot %s">%s</span>',
            esc_attr($entry['class']),
            esc_html($entry['label'])
        );
    }

    public static function dotClass(string $status): string
    {
        return match ($status) {
            'published' => 'bsf-status-dot-published',
            'archived' => 'bsf-status-dot-archived',
            default => 'bsf-status-dot-draft',
        };
    }
}
