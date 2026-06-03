<?php

declare(strict_types=1);

namespace BsFabric\Admin;

use BsFabric\Admin\Ajax\EntryAjax;
use BsFabric\Admin\Ajax\FieldAjax;
use BsFabric\Admin\Ajax\TemplateAjax;
use BsFabric\Admin\Components\AdminShell;
use BsFabric\Admin\DocsExampleRenderer;
use BsFabric\Admin\Screens\DashboardScreen;
use BsFabric\Admin\Screens\DocsScreen;
use BsFabric\Admin\Screens\EntryEditScreen;
use BsFabric\Admin\Screens\EntryListScreen;
use BsFabric\Admin\Screens\ExplorerScreen;
use BsFabric\Admin\Screens\FieldsScreen;
use BsFabric\Admin\Screens\SchemaScreen;
use BsFabric\Admin\Screens\TemplatesScreen;
use BsFabric\Core\Capabilities;
use BsFabric\Core\PluginSettings;
use BsFabric\Templates\TemplateRepository;
use BsFabric\Templates\TemplateSettings;

final class MenuManager
{
    public const MENU_SLUG = 'bs-fabric';

    public function __construct(
        private readonly TemplateRepository $templateRepository,
        private readonly DashboardScreen $dashboardScreen,
        private readonly ExplorerScreen $explorerScreen,
        private readonly EntryListScreen $entryListScreen,
        private readonly EntryEditScreen $entryEditScreen,
        private readonly SchemaScreen $schemaScreen,
        private readonly DocsScreen $docsScreen,
    ) {
    }

    public function register(): void
    {
        AdminShell::registerHooks();
        add_action('admin_menu', [$this, 'registerMenus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminMenuAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAdminMenuAssets(string $hookSuffix): void
    {
        if (! $this->isFabricPage($hookSuffix)) {
            return;
        }

        wp_enqueue_style(
            'bs-fabric-admin-menu',
            BS_FABRIC_URL . 'assets/css/admin-menu.css',
            [],
            BS_FABRIC_VERSION
        );
    }

    public function registerMenus(): void
    {
        add_menu_page(
            PluginSettings::menuLabel(),
            PluginSettings::menuLabel(),
            Capabilities::MANAGE_ENTRIES,
            self::MENU_SLUG,
            [$this->dashboardScreen, 'render'],
            'dashicons-networking',
            58
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Übersicht', 'bs-fabric'),
            __('Übersicht', 'bs-fabric'),
            Capabilities::MANAGE_ENTRIES,
            self::MENU_SLUG,
            [$this->dashboardScreen, 'render']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Explorer', 'bs-fabric'),
            __('Explorer', 'bs-fabric'),
            Capabilities::MANAGE_ENTRIES,
            ExplorerScreen::SLUG,
            [$this->explorerScreen, 'render']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Einstellungen', 'bs-fabric'),
            __('Einstellungen', 'bs-fabric'),
            Capabilities::MANAGE_ENTRIES,
            SchemaScreen::SLUG,
            [$this->schemaScreen, 'render']
        );

        foreach ($this->templateRepository->getAll() as $template) {
            if (! TemplateSettings::showInMenu($template->settings)) {
                continue;
            }

            add_submenu_page(
                self::MENU_SLUG,
                $template->label,
                $template->label,
                Capabilities::MANAGE_ENTRIES,
                EntryListScreen::slugForTemplate($template->name),
                fn (): mixed => $this->entryListScreen->renderForTemplate($template)
            );
        }

        add_submenu_page(
            null,
            __('Templates', 'bs-fabric'),
            __('Templates', 'bs-fabric'),
            Capabilities::MANAGE_SCHEMA,
            TemplatesScreen::LEGACY_SLUG,
            static function (): void {
                wp_safe_redirect(SchemaScreen::url(SchemaScreen::TAB_TEMPLATES));
                exit;
            }
        );

        add_submenu_page(
            null,
            __('Fields', 'bs-fabric'),
            __('Fields', 'bs-fabric'),
            Capabilities::MANAGE_SCHEMA,
            FieldsScreen::LEGACY_SLUG,
            static function (): void {
                wp_safe_redirect(SchemaScreen::url(SchemaScreen::TAB_FIELDS));
                exit;
            }
        );

        add_submenu_page(
            null,
            __('Doku', 'bs-fabric'),
            __('Doku', 'bs-fabric'),
            Capabilities::MANAGE_ENTRIES,
            DocsScreen::LEGACY_SLUG,
            static function (): void {
                wp_safe_redirect(SchemaScreen::url(SchemaScreen::TAB_DOCS));
                exit;
            }
        );

        add_submenu_page(
            null,
            __('Entry bearbeiten', 'bs-fabric'),
            __('Entry bearbeiten', 'bs-fabric'),
            Capabilities::MANAGE_ENTRIES,
            EntryEditScreen::SLUG,
            [$this->entryEditScreen, 'render']
        );
    }

    public function enqueueAssets(string $hookSuffix): void
    {
        if (! $this->isFabricPage($hookSuffix)) {
            return;
        }

        if ($this->isExplorerPage($hookSuffix)) {
            $this->explorerScreen->enqueueAssets();
        }

        if ($this->isDocsPage($hookSuffix)) {
            $this->docsScreen->enqueueAssets();
        }

        if ($this->isTemplateEditPage($hookSuffix)) {
            DocsExampleRenderer::enqueueAssets(false);
        }

        if ($this->isSchemaAdminPage($hookSuffix)) {
            wp_enqueue_style(
                'bs-fabric-bsf-schema',
                BS_FABRIC_URL . 'assets/css/bsf-schema.css',
                ['bs-fabric-bsf-components'],
                BS_FABRIC_VERSION
            );

            wp_enqueue_script(
                'bs-fabric-bsf-schema',
                BS_FABRIC_URL . 'assets/js/bsf-schema.js',
                ['bs-fabric-bsf-ui'],
                BS_FABRIC_VERSION,
                true
            );
        }

        if ($this->isEntryWorkflowPage($hookSuffix) || $this->isDocsPage($hookSuffix)) {
            wp_enqueue_style(
                'bs-fabric-bsf-entries',
                BS_FABRIC_URL . 'assets/css/bsf-entries.css',
                ['bs-fabric-bsf-components'],
                BS_FABRIC_VERSION
            );
        }

        AdminShell::enqueueAssets();

        wp_enqueue_style(
            'bs-fabric-admin',
            BS_FABRIC_URL . 'assets/css/admin.css',
            [],
            BS_FABRIC_VERSION
        );

        $scriptDependencies = [];

        if ($this->isTemplateEditPage($hookSuffix) || $this->isEntryEditPage($hookSuffix)) {
            wp_enqueue_script(
                'bs-fabric-sortable',
                BS_FABRIC_URL . 'assets/js/sortable.min.js',
                [],
                BS_FABRIC_VERSION,
                true
            );
            $scriptDependencies[] = 'bs-fabric-sortable';
        }

        if ($this->isEntryEditPage($hookSuffix)) {
            wp_enqueue_media();

            $codeEditorSettings = wp_enqueue_code_editor(['type' => 'text/html']);
            $entryScriptDeps = $scriptDependencies;

            if ($codeEditorSettings !== false) {
                wp_enqueue_script('wp-theme-plugin-editor');
                wp_enqueue_style('wp-codemirror');
                $entryScriptDeps[] = 'wp-theme-plugin-editor';
            }

            wp_enqueue_script(
                'bs-fabric-entry-admin',
                BS_FABRIC_URL . 'assets/js/entry-admin.js',
                $entryScriptDeps,
                BS_FABRIC_VERSION,
                true
            );

            wp_localize_script(
                'bs-fabric-entry-admin',
                'bsFabricEntryAdmin',
                [
                    'codeEditor' => is_array($codeEditorSettings) ? $codeEditorSettings : [],
                ]
            );
        }

        wp_enqueue_script(
            'bs-fabric-admin',
            BS_FABRIC_URL . 'assets/js/admin.js',
            array_merge($scriptDependencies, ['bs-fabric-bsf-ui']),
            BS_FABRIC_VERSION,
            true
        );

        wp_localize_script(
            'bs-fabric-admin',
            'bsFabricAdmin',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'fieldNonce' => wp_create_nonce(FieldAjax::NONCE_ACTION),
                'templateNonce' => wp_create_nonce(TemplateAjax::NONCE_ACTION),
                'entryNonce' => wp_create_nonce(EntryAjax::NONCE_ACTION),
                'strings' => [
                    'confirmDeleteField' => __('Dieses Feld wirklich löschen?', 'bs-fabric'),
                    'confirmDeleteTemplate' => __('Dieses Template wirklich löschen?', 'bs-fabric'),
                    'confirmDeleteEntry' => __('Diesen Entry wirklich löschen?', 'bs-fabric'),
                    'deleteError' => __('Löschen fehlgeschlagen.', 'bs-fabric'),
                    'assignError' => __('Feld konnte nicht zugewiesen werden.', 'bs-fabric'),
                    'removeError' => __('Feld konnte nicht entfernt werden.', 'bs-fabric'),
                    'orderError' => __('Reihenfolge konnte nicht gespeichert werden.', 'bs-fabric'),
                    'relationSearchError' => __('Suche fehlgeschlagen.', 'bs-fabric'),
                    'relationExists' => __('Entry ist bereits verknüpft.', 'bs-fabric'),
                    'bulkSelectedOne' => __('1 ausgewählt', 'bs-fabric'),
                    'bulkSelectedMany' => __('ausgewählt', 'bs-fabric'),
                    'confirmBulkPublish' => __('Ausgewählte Einträge veröffentlichen?', 'bs-fabric'),
                    'confirmBulkDuplicate' => __('Ausgewählte Einträge duplizieren?', 'bs-fabric'),
                    'confirmBulkDelete' => __('Ausgewählte Einträge wirklich löschen?', 'bs-fabric'),
                    'bulkError' => __('Bulk-Aktion fehlgeschlagen.', 'bs-fabric'),
                ],
            ]
        );

        if ($this->isEntryListPage($hookSuffix)) {
            wp_enqueue_script(
                'bs-fabric-entry-list',
                BS_FABRIC_URL . 'assets/js/entry-list.js',
                ['bs-fabric-admin'],
                BS_FABRIC_VERSION,
                true
            );
        }
    }

    private function isFabricPage(string $hookSuffix): bool
    {
        return str_contains($hookSuffix, self::MENU_SLUG);
    }

    private function isTemplateEditPage(string $hookSuffix): bool
    {
        if (! SchemaScreen::isTab(SchemaScreen::TAB_TEMPLATES)) {
            return false;
        }

        return isset($_GET['action'])
            && sanitize_key((string) wp_unslash($_GET['action'])) === 'edit';
    }

    private function isEntryEditPage(string $hookSuffix): bool
    {
        return str_contains($hookSuffix, EntryEditScreen::SLUG);
    }

    private function isExplorerPage(string $hookSuffix): bool
    {
        return str_contains($hookSuffix, ExplorerScreen::SLUG);
    }

    private function isDocsPage(string $hookSuffix): bool
    {
        return SchemaScreen::isTab(SchemaScreen::TAB_DOCS);
    }

    private function isSchemaAdminPage(string $hookSuffix): bool
    {
        return str_contains($hookSuffix, SchemaScreen::SLUG);
    }

    private function isEntryListPage(string $hookSuffix): bool
    {
        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

        return EntryListScreen::resolveTemplateNameFromPage($page) !== null;
    }

    private function isEntryWorkflowPage(string $hookSuffix): bool
    {
        return $this->isEntryEditPage($hookSuffix)
            || $this->isEntryListPage($hookSuffix)
            || $this->isExplorerPage($hookSuffix);
    }
}
