<?php

declare(strict_types=1);

namespace BsFabric\Admin\Components;

final class Button
{
    /**
     * @param array{
     *     variant?: string,
     *     size?: string,
     *     icon?: string,
     *     href?: string,
     *     type?: string,
     *     class?: string,
     *     id?: string,
     *     disabled?: bool,
     *     form?: string
     * } $attrs
     */
    public static function render(string $label, array $attrs = []): string
    {
        $variant = $attrs['variant'] ?? 'ghost';
        $size = $attrs['size'] ?? '';
        $icon = $attrs['icon'] ?? '';
        $href = $attrs['href'] ?? '';
        $type = $attrs['type'] ?? 'button';
        $extraClass = $attrs['class'] ?? '';
        $id = $attrs['id'] ?? '';
        $disabled = (bool) ($attrs['disabled'] ?? false);
        $form = $attrs['form'] ?? '';

        $classes = array_filter([
            'bsf-btn',
            $variant !== 'ghost' ? 'bsf-btn-' . $variant : 'bsf-btn-ghost',
            $size === 'sm' ? 'bsf-btn-sm' : '',
            $extraClass,
        ]);

        $classAttr = esc_attr(implode(' ', $classes));
        $idAttr = $id !== '' ? ' id="' . esc_attr($id) . '"' : '';
        $disabledAttr = $disabled ? ' disabled' : '';
        $formAttr = $form !== '' ? ' form="' . esc_attr($form) . '"' : '';

        $iconHtml = $icon !== '' ? Icon::render($icon, $size === 'sm' ? 15 : 16) : '';
        $content = $iconHtml . esc_html($label);

        if ($href !== '') {
            return sprintf(
                '<a href="%s" class="%s"%s%s>%s</a>',
                esc_url($href),
                $classAttr,
                $idAttr,
                $disabled ? ' aria-disabled="true"' : '',
                $content
            );
        }

        return sprintf(
            '<button type="%s" class="%s"%s%s%s>%s</button>',
            esc_attr($type),
            $classAttr,
            $idAttr,
            $formAttr,
            $disabledAttr,
            $content
        );
    }
}
