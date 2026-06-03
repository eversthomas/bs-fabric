<?php

declare(strict_types=1);

namespace BsFabric\Admin\Components;

use BsFabric\Fields\FieldRegistry;

final class TypeBadge
{
    public static function render(string $type, FieldRegistry $registry): string
    {
        $label = $registry->has($type)
            ? $registry->get($type)->getLabel()
            : $type;
        $icon = FieldTypeCatalog::iconFor($type);

        return sprintf(
            '<span class="bsf-ftype"><span class="bsf-ftype-icon">%s</span><span>%s</span></span>',
            Icon::render($icon, 13),
            esc_html($label)
        );
    }
}
