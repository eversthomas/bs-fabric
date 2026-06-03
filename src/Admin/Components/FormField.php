<?php

declare(strict_types=1);

namespace BsFabric\Admin\Components;

final class FormField
{
    public static function render(
        string $label,
        string $inputHtml,
        string $hint = '',
        bool $required = false,
        string $optionalLabel = '',
    ): string {
        $html = '<div class="bsf-field">';
        $html .= '<label class="bsf-flabel">';
        $html .= esc_html($label);

        if ($required) {
            $html .= ' <span class="bsf-req">*</span>';
        }

        if ($optionalLabel !== '') {
            $html .= ' <span class="bsf-opt">' . esc_html($optionalLabel) . '</span>';
        }

        $html .= '</label>';
        $html .= $inputHtml;

        if ($hint !== '') {
            $html .= '<p class="bsf-fhint">' . esc_html($hint) . '</p>';
        }

        $html .= '</div>';

        return $html;
    }
}
