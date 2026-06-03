<?php

declare(strict_types=1);

namespace BsFabric\Admin\Components;

final class PageHead
{
    public static function render(string $title, string $lede = '', string $actionsHtml = ''): void
    {
        echo '<div class="bsf-page-head">';
        echo '<div class="bsf-grow">';
        echo '<h1>' . esc_html($title) . '</h1>';

        if ($lede !== '') {
            echo '<p class="bsf-page-lede">' . esc_html($lede) . '</p>';
        }

        echo '</div>';

        if ($actionsHtml !== '') {
            echo '<div class="bsf-page-actions">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller uses Button::render / escaped URLs
            echo $actionsHtml;
            echo '</div>';
        }

        echo '</div>';
    }
}
