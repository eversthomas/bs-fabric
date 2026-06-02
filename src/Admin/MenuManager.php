<?php

declare(strict_types=1);

namespace BsFabric\Admin;

use BsFabric\Admin\Ajax\EntryAjax;
use BsFabric\Admin\Ajax\FieldAjax;
use BsFabric\Admin\Ajax\TemplateAjax;
use BsFabric\Admin\Screens\EntryEditScreen;
use BsFabric\Admin\Screens\EntryListScreen;
use BsFabric\Admin\Screens\FieldsScreen;
use BsFabric\Admin\Screens\TemplatesScreen;
use BsFabric\Core\Capabilities;
use BsFabric\Templates\TemplateRepository;

final class MenuManager
{
    public const MENU_SLUG = 'bs-fabric';

    public function __construct(
        private readonly TemplateRepository $templateRepository,
        private readonly EntryListScreen $entryListScreen,
        private readonly EntryEditScreen $entryEditScreen,
        private readonly TemplatesScreen $templatesScreen,
        private readonly FieldsScreen $fieldsScreen,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerMenus(): void
    {
        add_menu_page(
            __('Fabric', 'bs-fabric'),
            __('Fabric', 'bs-fabric'),
            Capabilities::MANAGE_ENTRIES,
            self::MENU_SLUG,
            [$this, 'renderDashboard'],
            'dashicons-networking',
            58
        );

        foreach ($this->templateRepository->getAll() as $template) {
            add_submenu_page(
                self::MENU_SLUG,
                $template->label,
                $template->label,
                Capabilities::MANAGE_ENTRIES,
                EntryListScreen::slugForTemplate($template->name),
                fn (): mixed => $this->entryListScreen->renderForTemplate($template)
            );
        }

        if (current_user_can(Capabilities::MANAGE_SCHEMA)) {
            add_submenu_page(
                self::MENU_SLUG,
                __('Templates', 'bs-fabric'),
                __('Templates', 'bs-fabric'),
                Capabilities::MANAGE_SCHEMA,
                TemplatesScreen::SLUG,
                [$this->templatesScreen, 'render']
            );

            add_submenu_page(
                self::MENU_SLUG,
                __('Fields', 'bs-fabric'),
                __('Fields', 'bs-fabric'),
                Capabilities::MANAGE_SCHEMA,
                FieldsScreen::SLUG,
                [$this->fieldsScreen, 'render']
            );
        }

        add_submenu_page(
            null,
            __('Entry bearbeiten', 'bs-fabric'),
            __('Entry bearbeiten', 'bs-fabric'),
            Capabilities::MANAGE_ENTRIES,
            EntryEditScreen::SLUG,
            [$this->entryEditScreen, 'render']
        );

        remove_submenu_page(self::MENU_SLUG, self::MENU_SLUG);
    }

    public function renderDashboard(): void
    {
        if (! current_user_can(Capabilities::MANAGE_ENTRIES)) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'bs-fabric'));
        }

        echo '<div class="wrap bs-fabric-wrap">';
        echo '<h1>' . esc_html__('Fabric', 'bs-fabric') . '</h1>';

        $templates = $this->templateRepository->getAll();

        if ($templates === []) {
            echo '<p>' . esc_html__('Noch keine Templates vorhanden.', 'bs-fabric') . '</p>';
        } else {
            echo '<p>' . esc_html__('Wähle ein Template, um Entries zu verwalten.', 'bs-fabric') . '</p>';
            echo '<ul class="bs-fabric-dashboard-templates">';

            foreach ($templates as $template) {
                printf(
                    '<li><a href="%s">%s</a></li>',
                    esc_url(admin_url('admin.php?page=' . EntryListScreen::slugForTemplate($template->name))),
                    esc_html($template->label)
                );
            }

            echo '</ul>';
        }

        if (current_user_can(Capabilities::MANAGE_SCHEMA)) {
            echo '<p>';
            echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=' . TemplatesScreen::SLUG)) . '">';
            echo esc_html__('Templates verwalten', 'bs-fabric') . '</a> ';
            echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=' . FieldsScreen::SLUG)) . '">';
            echo esc_html__('Felder verwalten', 'bs-fabric') . '</a>';
            echo '</p>';
        }

        echo '</div>';
    }

    public function enqueueAssets(string $hookSuffix): void
    {
        if (! $this->isFabricPage($hookSuffix)) {
            return;
        }

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
            $scriptDependencies,
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
                ],
            ]
        );
    }

    private function isFabricPage(string $hookSuffix): bool
    {
        return str_contains($hookSuffix, self::MENU_SLUG);
    }

    private function isTemplateEditPage(string $hookSuffix): bool
    {
        return str_contains($hookSuffix, TemplatesScreen::SLUG)
            && isset($_GET['action'])
            && sanitize_key((string) wp_unslash($_GET['action'])) === 'edit';
    }

    private function isEntryEditPage(string $hookSuffix): bool
    {
        return str_contains($hookSuffix, EntryEditScreen::SLUG);
    }
}
