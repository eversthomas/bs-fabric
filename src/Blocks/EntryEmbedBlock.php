<?php

declare(strict_types=1);

namespace BsFabric\Blocks;

use BsFabric\Admin\Ajax\EntryEmbedAjax;
use BsFabric\Frontend\EntryEmbedRenderer;

/**
 * Gutenberg-Block „Fabric-Inhalt" — Komfort-Oberfläche für dasselbe, was der
 * `[bs_fabric]`-Shortcode kann. Beide teilen sich EntryEmbedRenderer, damit
 * nichts doppelt gepflegt werden muss und keine Funktion an Gutenberg hängt.
 */
final class EntryEmbedBlock
{
    private const EDITOR_SCRIPT_HANDLE = 'bs-fabric-entry-embed-editor-script';

    public function __construct(
        private readonly EntryEmbedRenderer $renderer,
    ) {
    }

    public function register(): void
    {
        $buildDir = BS_FABRIC_PATH . 'assets/build/blocks/entry-embed';

        if (! file_exists($buildDir . '/block.json')) {
            return;
        }

        register_block_type($buildDir, [
            'render_callback' => [$this, 'render'],
        ]);

        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
    }

    public function enqueueEditorAssets(): void
    {
        if (! wp_script_is(self::EDITOR_SCRIPT_HANDLE, 'registered')) {
            return;
        }

        wp_localize_script(self::EDITOR_SCRIPT_HANDLE, 'bsFabricEntryEmbed', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(EntryEmbedAjax::NONCE_ACTION),
            'strings' => [
                'title' => __('Fabric-Inhalt', 'bs-fabric'),
                'templateLabel' => __('Template', 'bs-fabric'),
                'templatePlaceholder' => __('— auswählen —', 'bs-fabric'),
                'viewLabel' => __('Ansicht', 'bs-fabric'),
                'noEntries' => __('Keine Einträge in diesem Template.', 'bs-fabric'),
                'placeholderInstructions' => __('Wähle rechts ein Template und mindestens einen Eintrag aus.', 'bs-fabric'),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes): string
    {
        $templateName = isset($attributes['template']) ? sanitize_key((string) $attributes['template']) : '';
        $ids = isset($attributes['entryIds']) && is_array($attributes['entryIds'])
            ? array_values(array_filter(array_map('absint', $attributes['entryIds'])))
            : [];
        $view = isset($attributes['view']) ? (string) $attributes['view'] : 'card';

        $html = $this->renderer->render($templateName, $ids, $view);

        if ($html !== '' || ! defined('REST_REQUEST') || ! REST_REQUEST) {
            return $html;
        }

        // Nur in der Editor-Vorschau (ServerSideRender läuft über die REST-API) einen
        // Hinweis zeigen — auf der echten Frontend-Seite bleibt der Block einfach leer.
        return '<p class="bs-fabric-embed-placeholder">'
            . esc_html__('Keine veröffentlichten Einträge ausgewählt.', 'bs-fabric')
            . '</p>';
    }
}
