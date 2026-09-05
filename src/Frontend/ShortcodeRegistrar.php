<?php

declare(strict_types=1);

namespace BsFabric\Frontend;

/**
 * `[bs_fabric template="referenz" ids="3,7" view="card"]` — Gutenberg-unabhängige
 * Basis zum Einbinden von Fabric-Inhalten. Nutzt dieselbe Rendering-Logik wie
 * der Gutenberg-Block, funktioniert also auch in Themes ohne Block-Editor.
 */
final class ShortcodeRegistrar
{
    public function __construct(
        private readonly EntryEmbedRenderer $renderer,
    ) {
    }

    public function register(): void
    {
        add_shortcode('bs_fabric', [$this, 'render']);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function render(array|string $atts): string
    {
        $atts = shortcode_atts(
            [
                'template' => '',
                'ids' => '',
                'view' => 'card',
            ],
            is_array($atts) ? $atts : [],
            'bs_fabric'
        );

        $ids = array_values(array_unique(array_filter(
            array_map('absint', explode(',', (string) $atts['ids']))
        )));

        return $this->renderer->render((string) $atts['template'], $ids, (string) $atts['view']);
    }
}
