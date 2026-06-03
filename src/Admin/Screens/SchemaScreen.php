<?php

declare(strict_types=1);

namespace BsFabric\Admin\Screens;

use BsFabric\Admin\Components\AdminShell;
use BsFabric\Core\Capabilities;

final class SchemaScreen
{
    public const SLUG = 'bs-fabric-schema';

    public const TAB_TEMPLATES = 'templates';

    public const TAB_FIELDS = 'fields';

    public const TAB_DOCS = 'docs';

    public const TAB_CONFIG = 'config';

    public function __construct(
        private readonly TemplatesScreen $templatesScreen,
        private readonly FieldsScreen $fieldsScreen,
        private readonly DocsScreen $docsScreen,
        private readonly PluginSettingsScreen $pluginSettingsScreen,
    ) {
    }

    public function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_ENTRIES)) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'bs-fabric'));
        }

        $tab = $this->resolveTab();

        AdminShell::open([
            ['label' => __('Einstellungen', 'bs-fabric'), 'url' => self::url($tab)],
            ['label' => $this->tabLabel($tab)],
        ]);

        echo '<div class="bsf-schema-panel">';
        $this->renderTabs($tab);

        match ($tab) {
            self::TAB_TEMPLATES => $this->templatesScreen->render(),
            self::TAB_FIELDS => $this->fieldsScreen->render(),
            self::TAB_CONFIG => $this->pluginSettingsScreen->render(),
            default => $this->docsScreen->render(),
        };

        echo '</div>';

        AdminShell::close();
    }

    public static function url(string $tab, array $args = []): string
    {
        return add_query_arg(
            array_merge(['page' => self::SLUG, 'tab' => $tab], $args),
            admin_url('admin.php')
        );
    }

    public static function isSchemaPage(string $page = ''): bool
    {
        if ($page === '') {
            $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        }

        return $page === self::SLUG;
    }

    public static function currentTab(): string
    {
        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : self::TAB_TEMPLATES;

        return in_array($tab, [self::TAB_TEMPLATES, self::TAB_FIELDS, self::TAB_DOCS, self::TAB_CONFIG], true)
            ? $tab
            : self::TAB_TEMPLATES;
    }

    public static function isTab(string $tab, string $page = ''): bool
    {
        return self::isSchemaPage($page) && self::currentTab() === $tab;
    }

    private function resolveTab(): string
    {
        $tab = self::currentTab();

        if ($tab === self::TAB_TEMPLATES && ! current_user_can(Capabilities::MANAGE_SCHEMA)) {
            return self::TAB_DOCS;
        }

        if ($tab === self::TAB_FIELDS && ! current_user_can(Capabilities::MANAGE_SCHEMA)) {
            return self::TAB_DOCS;
        }

        if ($tab === self::TAB_CONFIG && ! current_user_can(Capabilities::MANAGE_SCHEMA)) {
            return self::TAB_DOCS;
        }

        return $tab;
    }

    private function renderTabs(string $activeTab): void
    {
        echo '<nav class="bsf-schema-tabs" aria-label="' . esc_attr__('Einstellungen-Bereiche', 'bs-fabric') . '">';

        if (current_user_can(Capabilities::MANAGE_SCHEMA)) {
            $this->renderTabLink(self::TAB_TEMPLATES, __('Templates', 'bs-fabric'), $activeTab);
            $this->renderTabLink(self::TAB_FIELDS, __('Fields', 'bs-fabric'), $activeTab);
            $this->renderTabLink(self::TAB_CONFIG, __('Konfiguration', 'bs-fabric'), $activeTab);
        }

        $this->renderTabLink(self::TAB_DOCS, __('Doku', 'bs-fabric'), $activeTab);
        echo '</nav>';
    }

    private function renderTabLink(string $tab, string $label, string $activeTab): void
    {
        $class = $activeTab === $tab ? 'bsf-active' : '';

        printf(
            '<a href="%s" class="%s">%s</a>',
            esc_url(self::url($tab)),
            esc_attr($class),
            esc_html($label)
        );
    }

    private function tabLabel(string $tab): string
    {
        return match ($tab) {
            self::TAB_FIELDS => __('Fields', 'bs-fabric'),
            self::TAB_DOCS => __('Doku', 'bs-fabric'),
            self::TAB_CONFIG => __('Konfiguration', 'bs-fabric'),
            default => __('Templates', 'bs-fabric'),
        };
    }
}
