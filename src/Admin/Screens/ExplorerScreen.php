<?php

declare(strict_types=1);

namespace BsFabric\Admin\Screens;

use BsFabric\Admin\Ajax\EntryAjax;
use BsFabric\Core\Capabilities;
use BsFabric\Entries\EntryRepository;
use BsFabric\Templates\TemplateRepository;

final class ExplorerScreen
{
    public const SLUG = 'bs-fabric-explorer';

    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly TemplateRepository $templateRepository,
    ) {
    }

    public function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_ENTRIES)) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'bs-fabric'));
        }

        echo '<div class="wrap bs-fabric-wrap bs-fabric-explorer">';
        echo '<h1>' . esc_html__('Explorer', 'bs-fabric') . '</h1>';
        echo '<div id="bs-fabric-explorer-root"></div>';
        echo '</div>';
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
        $statusLabels = [
            'draft' => __('Entwurf', 'bs-fabric'),
            'published' => __('Veröffentlicht', 'bs-fabric'),
            'archived' => __('Archiviert', 'bs-fabric'),
        ];

        $templates = [];

        foreach ($this->templateRepository->getAll() as $template) {
            $templates[] = [
                'id' => $template->getId(),
                'label' => $template->label,
            ];
        }

        $entries = [];

        foreach ($this->entryRepository->getExplorerRows() as $row) {
            $createdAt = $row['created_at'];
            $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;
            $status = $row['status'];

            $entries[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'template_id' => $row['template_id'],
                'template_label' => $row['template_label'],
                'status' => $status,
                'status_label' => $statusLabels[$status] ?? $statusLabels['draft'],
                'created_at' => $createdAt,
                'created_at_label' => $timestamp !== false
                    ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)
                    : '—',
                'editUrl' => add_query_arg(
                    [
                        'page' => EntryEditScreen::SLUG,
                        'template_id' => $row['template_id'],
                        'entry_id' => $row['id'],
                    ],
                    admin_url('admin.php')
                ),
            ];
        }

        return [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'entryNonce' => wp_create_nonce(EntryAjax::NONCE_ACTION),
            'entries' => $entries,
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
            ],
        ];
    }
}
