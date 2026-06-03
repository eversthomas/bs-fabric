<?php

declare(strict_types=1);

namespace BsFabric\Admin\Screens;

use BsFabric\Admin\Ajax\EntryAjax;
use BsFabric\Admin\Components\AdminShell;
use BsFabric\Admin\Components\PageHead;
use BsFabric\Admin\ExplorerEntryFormatter;
use BsFabric\Core\Capabilities;
use BsFabric\Templates\TemplateRepository;

final class ExplorerScreen
{
    public const SLUG = 'bs-fabric-explorer';

    public function __construct(
        private readonly TemplateRepository $templateRepository,
    ) {
    }

    public function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_ENTRIES)) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'bs-fabric'));
        }

        AdminShell::open([
            ['label' => __('Explorer', 'bs-fabric')],
        ]);

        PageHead::render(
            __('Explorer', 'bs-fabric'),
            __('Alle Einträge über alle Templates hinweg.', 'bs-fabric')
        );

        echo '<div id="bs-fabric-explorer-root"></div>';

        AdminShell::close();
    }

    public function enqueueAssets(): void
    {
        $assetFile = BS_FABRIC_PATH . 'assets/build/explorer/index.asset.php';

        if (! file_exists($assetFile)) {
            return;
        }

        /** @var array{dependencies: list<string>, version: string} $asset */
        $asset = include $assetFile;

        wp_enqueue_style('wp-components');

        $styleFile = BS_FABRIC_PATH . 'assets/build/explorer/style-index.css';

        if (file_exists($styleFile)) {
            wp_enqueue_style(
                'bs-fabric-explorer',
                BS_FABRIC_URL . 'assets/build/explorer/style-index.css',
                ['wp-components'],
                (string) filemtime($styleFile)
            );
        }

        wp_enqueue_script(
            'bs-fabric-explorer',
            BS_FABRIC_URL . 'assets/build/explorer/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_localize_script(
            'bs-fabric-explorer',
            'bsFabricExplorer',
            $this->buildScriptConfig()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildScriptConfig(): array
    {
        $statusLabels = ExplorerEntryFormatter::statusLabels();

        $templates = [];

        foreach ($this->templateRepository->getAll() as $template) {
            $templates[] = [
                'id' => $template->getId(),
                'label' => $template->label,
            ];
        }

        return [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'entryNonce' => wp_create_nonce(EntryAjax::NONCE_ACTION),
            'entries' => [],
            'templates' => $templates,
            'statusLabels' => $statusLabels,
            'strings' => [
                'title' => __('Titel', 'bs-fabric'),
                'template' => __('Template', 'bs-fabric'),
                'status' => __('Status', 'bs-fabric'),
                'createdAt' => __('Erstellt am', 'bs-fabric'),
                'edit' => __('Bearbeiten', 'bs-fabric'),
                'delete' => __('Löschen', 'bs-fabric'),
                'search' => __('Entries suchen…', 'bs-fabric'),
                'confirmDeleteEntry' => __('Diesen Entry wirklich löschen?', 'bs-fabric'),
                'deleteError' => __('Löschen fehlgeschlagen.', 'bs-fabric'),
                'loadError' => __('Einträge konnten nicht geladen werden.', 'bs-fabric'),
            ],
        ];
    }
}
