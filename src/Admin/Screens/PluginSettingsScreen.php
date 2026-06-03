<?php

declare(strict_types=1);

namespace BsFabric\Admin\Screens;

use BsFabric\Admin\Components\Button;
use BsFabric\Admin\Components\FormField;
use BsFabric\Admin\Components\Icon;
use BsFabric\Admin\Components\PageHead;
use BsFabric\Admin\Components\SchemaNotices;
use BsFabric\Admin\SchemaExporter;
use BsFabric\Core\Capabilities;
use BsFabric\Core\PluginSettings;
use BsFabric\Entries\EntryRepository;

final class PluginSettingsScreen
{
    public const NONCE_ACTION = 'bs_fabric_plugin_settings';

    public const SECTION_GENERAL = 'general';

    public const SECTION_ROLES = 'roles';

    public const SECTION_API = 'api';

    public const SECTION_ADVANCED = 'advanced';

    public function __construct(
        private readonly SchemaExporter $schemaExporter,
        private readonly EntryRepository $entryRepository,
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handleActions']);
    }

    public function handleActions(): void
    {
        if (! SchemaScreen::isSchemaPage() || ! SchemaScreen::isTab(SchemaScreen::TAB_CONFIG)) {
            return;
        }

        if (! current_user_can(Capabilities::MANAGE_SCHEMA)) {
            return;
        }

        if (isset($_GET['bs_fabric_export']) && sanitize_key((string) wp_unslash($_GET['bs_fabric_export'])) === 'schema') {
            $this->handleSchemaExport();

            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['bs_fabric_action'])
            ? sanitize_key((string) wp_unslash($_POST['bs_fabric_action']))
            : '';

        match ($action) {
            'save_settings' => $this->handleSave(),
            'reset_entries' => $this->handleResetEntries(),
            default => null,
        };
    }

    public function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SCHEMA)) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'bs-fabric'));
        }

        SchemaNotices::enqueueToastScript();

        $section = self::currentSection();
        $settings = PluginSettings::get();

        PageHead::render(
            __('Konfiguration', 'bs-fabric'),
            __('Verhalten von Fabric für diese WordPress-Installation.', 'bs-fabric')
        );

        echo '<div class="bsf-settings-layout">';
        $this->renderSectionNav($section);

        echo '<div class="bsf-settings-content">';
        match ($section) {
            self::SECTION_ROLES => $this->renderRolesSection(),
            self::SECTION_API => $this->renderApiSection(),
            self::SECTION_ADVANCED => $this->renderAdvancedSection($settings),
            default => $this->renderGeneralSection($settings),
        };
        echo '</div></div>';
    }

    public static function currentSection(): string
    {
        $section = isset($_GET['section'])
            ? sanitize_key((string) wp_unslash($_GET['section']))
            : self::SECTION_GENERAL;

        return in_array(
            $section,
            [self::SECTION_GENERAL, self::SECTION_ROLES, self::SECTION_API, self::SECTION_ADVANCED],
            true
        ) ? $section : self::SECTION_GENERAL;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function renderGeneralSection(array $settings): void
    {
        echo '<form method="post" action="' . esc_url($this->pageUrl(self::SECTION_GENERAL)) . '" class="bsf-settings-form">';
        wp_nonce_field(self::NONCE_ACTION . '_save');
        echo '<input type="hidden" name="bs_fabric_action" value="save_settings" />';

        echo '<div class="bsf-card">';
        echo '<div class="bsf-card-head"><h3>' . esc_html__('Allgemein', 'bs-fabric') . '</h3></div>';
        echo '<div class="bsf-card-body">';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo FormField::render(
            __('Menü-Bezeichnung', 'bs-fabric'),
            sprintf(
                '<input class="bsf-input" name="%s" type="text" value="%s" maxlength="50" style="max-width:320px" />',
                esc_attr(PluginSettings::MENU_LABEL),
                esc_attr((string) $settings[PluginSettings::MENU_LABEL])
            ),
            __('So heißt Fabric im WordPress-Adminmenü.', 'bs-fabric')
        );

        $statusSelect = '<div class="bsf-select-wrap" style="max-width:320px"><select class="bsf-select" name="' . esc_attr(PluginSettings::DEFAULT_ENTRY_STATUS) . '">';

        foreach ([
            'draft' => __('Entwurf', 'bs-fabric'),
            'published' => __('Veröffentlicht', 'bs-fabric'),
            'archived' => __('Archiviert', 'bs-fabric'),
        ] as $value => $label) {
            $statusSelect .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected((string) $settings[PluginSettings::DEFAULT_ENTRY_STATUS], $value, false),
                esc_html($label)
            );
        }

        $statusSelect .= '</select>' . Icon::render('chevd', 15) . '</div>';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo FormField::render(
            __('Standard-Status neuer Einträge', 'bs-fabric'),
            $statusSelect
        );

        echo '<hr class="bsf-divider" />';

        $this->renderSwitchRow(
            PluginSettings::LOCK_FIELD_KEYS,
            __('Feld-Keys sperren', 'bs-fabric'),
            __('Keys sind nach Anlage nicht mehr änderbar — empfohlen für stabile Templates.', 'bs-fabric'),
            ! empty($settings[PluginSettings::LOCK_FIELD_KEYS])
        );

        echo '<div style="margin-top:18px">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Button::render(__('Speichern', 'bs-fabric'), [
            'variant' => 'primary',
            'icon' => 'save',
            'type' => 'submit',
        ]);
        echo '</div></div></div></form>';
    }

    private function renderRolesSection(): void
    {
        echo '<div class="bsf-card">';
        echo '<div class="bsf-card-head"><h3>' . esc_html__('Wer darf was?', 'bs-fabric') . '</h3></div>';
        echo '<table class="bsf-tbl bsf-settings-matrix">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Rolle', 'bs-fabric') . '</th>';
        echo '<th>' . esc_html__('Felder & Templates', 'bs-fabric') . '</th>';
        echo '<th>' . esc_html__('Einträge verwalten', 'bs-fabric') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($this->roleMatrix() as $row) {
            echo '<tr>';
            echo '<td class="bsf-cell-strong">' . esc_html($row['label']) . '</td>';
            echo '<td>' . $this->renderMatrixCheck($row['schema']) . '</td>';
            echo '<td>' . $this->renderMatrixCheck($row['entries']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<div class="bsf-card-body" style="padding-top:0">';
        echo '<p class="bsf-muted">' . esc_html__(
            'Fabric nutzt WordPress-Capabilities. Feinere Rechte pro Rolle kannst du über ein Rollen-Plugin oder functions.php anpassen.',
            'bs-fabric'
        ) . '</p>';
        echo '</div></div>';
    }

    private function renderApiSection(): void
    {
        $exportUrl = wp_nonce_url(
            add_query_arg(
                [
                    'page' => SchemaScreen::SLUG,
                    'tab' => SchemaScreen::TAB_CONFIG,
                    'section' => self::SECTION_API,
                    'bs_fabric_export' => 'schema',
                ],
                admin_url('admin.php')
            ),
            self::NONCE_ACTION . '_export'
        );

        echo '<div class="bsf-card">';
        echo '<div class="bsf-card-head"><h3>' . esc_html__('Export', 'bs-fabric') . '</h3></div>';
        echo '<div class="bsf-card-body">';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo FormField::render(
            __('Schema exportieren', 'bs-fabric'),
            Button::render(__('JSON exportieren', 'bs-fabric'), [
                'variant' => 'ghost',
                'icon' => 'box',
                'href' => $exportUrl,
            ]),
            __('Templates, Feldzuweisungen und Felddefinitionen als JSON-Datei.', 'bs-fabric')
        );

        echo '</div></div>';

        echo '<div class="bsf-card">';
        echo '<div class="bsf-card-head"><h3>' . esc_html__('REST-API', 'bs-fabric') . '</h3></div>';
        echo '<div class="bsf-card-body">';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo FormField::render(
            __('Geplanter Basis-Endpoint', 'bs-fabric'),
            '<input class="bsf-input bsf-input-mono" type="text" readonly value="' . esc_attr(rest_url('bs-fabric/v1/')) . '" />',
            __('Die REST-API ist noch nicht aktiv — Export und Theme-API (bs_fabric()) sind derzeit die Integrationsebenen.', 'bs-fabric')
        );

        echo '</div></div>';
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function renderAdvancedSection(array $settings): void
    {
        echo '<form method="post" action="' . esc_url($this->pageUrl(self::SECTION_ADVANCED)) . '" class="bsf-settings-form">';
        wp_nonce_field(self::NONCE_ACTION . '_save');
        echo '<input type="hidden" name="bs_fabric_action" value="save_settings" />';

        echo '<div class="bsf-card">';
        echo '<div class="bsf-card-head"><h3>' . esc_html__('Erweitert', 'bs-fabric') . '</h3></div>';
        echo '<div class="bsf-card-body">';

        $this->renderSwitchRow(
            PluginSettings::DELETE_ON_UNINSTALL,
            __('Daten beim Deinstallieren löschen', 'bs-fabric'),
            __('Entfernt alle Templates, Felder und Einträge unwiderruflich, wenn du das Plugin löschst.', 'bs-fabric'),
            ! empty($settings[PluginSettings::DELETE_ON_UNINSTALL])
        );

        echo '<div style="margin-top:18px">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Button::render(__('Speichern', 'bs-fabric'), [
            'variant' => 'primary',
            'icon' => 'save',
            'type' => 'submit',
        ]);
        echo '</div></div></div></form>';

        echo '<form method="post" action="' . esc_url($this->pageUrl(self::SECTION_ADVANCED)) . '" class="bsf-settings-form" style="margin-top:var(--gap)" onsubmit="return confirm(' . esc_attr__('Alle Einträge wirklich löschen? Templates und Felder bleiben erhalten.', 'bs-fabric') . ');">';
        wp_nonce_field(self::NONCE_ACTION . '_reset');
        echo '<input type="hidden" name="bs_fabric_action" value="reset_entries" />';
        echo '<div class="bsf-card">';
        echo '<div class="bsf-card-head"><h3>' . esc_html__('Daten zurücksetzen', 'bs-fabric') . '</h3></div>';
        echo '<div class="bsf-card-body">';
        echo '<p class="bsf-muted">' . esc_html__(
            'Löscht alle Einträge und deren Feldwerte. Templates und die Feld-Bibliothek bleiben unverändert.',
            'bs-fabric'
        ) . '</p>';
        echo '<div style="margin-top:14px">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Button::render(__('Alle Einträge löschen', 'bs-fabric'), [
            'variant' => 'danger',
            'icon' => 'trash',
            'type' => 'submit',
        ]);
        echo '</div></div></div></form>';
    }

    private function renderSectionNav(string $activeSection): void
    {
        echo '<nav class="bsf-settings-nav bsf-card" aria-label="' . esc_attr__('Konfigurationsbereiche', 'bs-fabric') . '">';

        foreach ([
            self::SECTION_GENERAL => __('Allgemein', 'bs-fabric'),
            self::SECTION_ROLES => __('Rollen & Rechte', 'bs-fabric'),
            self::SECTION_API => __('API & Export', 'bs-fabric'),
            self::SECTION_ADVANCED => __('Erweitert', 'bs-fabric'),
        ] as $section => $label) {
            $class = $activeSection === $section ? 'bsf-active' : '';
            printf(
                '<a href="%s" class="bsf-settings-nav-item %s">%s</a>',
                esc_url($this->pageUrl($section)),
                esc_attr($class),
                esc_html($label)
            );
        }

        echo '</nav>';
    }

    private function renderSwitchRow(string $name, string $title, string $hint, bool $checked): void
    {
        $inputId = 'bs-fabric-setting-' . sanitize_html_class($name);

        echo '<div class="bsf-switch-row">';
        echo '<div><span class="bsf-flabel" style="margin:0">' . esc_html($title) . '</span>';
        echo '<p class="bsf-fhint" style="margin-top:4px">' . esc_html($hint) . '</p></div>';
        printf(
            '<input type="checkbox" class="bsf-hidden-input" name="%s" id="%s" value="1"%s />',
            esc_attr($name),
            esc_attr($inputId),
            checked($checked, true, false)
        );
        echo '<button type="button" class="bsf-switch" data-for="' . esc_attr($inputId) . '"></button>';
        echo '</div>';
    }

    private function renderMatrixCheck(bool $enabled): string
    {
        if ($enabled) {
            return '<span class="bsf-settings-check is-on" aria-hidden="true">' . Icon::render('check', 14) . '</span>';
        }

        return '<span class="bsf-settings-check" aria-hidden="true">—</span>';
    }

    /**
     * @return list<array{label: string, schema: bool, entries: bool}>
     */
    private function roleMatrix(): array
    {
        return [
            [
                'label' => __('Administrator', 'bs-fabric'),
                'schema' => true,
                'entries' => true,
            ],
            [
                'label' => __('Redakteur', 'bs-fabric'),
                'schema' => false,
                'entries' => true,
            ],
        ];
    }

    private function handleSave(): void
    {
        if (! $this->verifyNonce(self::NONCE_ACTION . '_save')) {
            wp_die(esc_html__('Sicherheitsüberprüfung fehlgeschlagen.', 'bs-fabric'));
        }

        $section = self::currentSection();
        $current = PluginSettings::get();
        $input = $_POST;

        if ($section === self::SECTION_ADVANCED) {
            $input = array_merge($current, [
                PluginSettings::DELETE_ON_UNINSTALL => isset($_POST[PluginSettings::DELETE_ON_UNINSTALL]),
            ]);
        } elseif ($section === self::SECTION_GENERAL) {
            $input = array_merge($current, [
                PluginSettings::MENU_LABEL => isset($_POST[PluginSettings::MENU_LABEL])
                    ? (string) wp_unslash($_POST[PluginSettings::MENU_LABEL])
                    : $current[PluginSettings::MENU_LABEL],
                PluginSettings::DEFAULT_ENTRY_STATUS => isset($_POST[PluginSettings::DEFAULT_ENTRY_STATUS])
                    ? (string) wp_unslash($_POST[PluginSettings::DEFAULT_ENTRY_STATUS])
                    : $current[PluginSettings::DEFAULT_ENTRY_STATUS],
                PluginSettings::LOCK_FIELD_KEYS => isset($_POST[PluginSettings::LOCK_FIELD_KEYS]),
            ]);
        } else {
            return;
        }

        PluginSettings::save($input);

        $this->redirectWithNotice('success', __('Einstellungen gespeichert.', 'bs-fabric'), $section);
    }

    private function handleResetEntries(): void
    {
        if (! $this->verifyNonce(self::NONCE_ACTION . '_reset')) {
            wp_die(esc_html__('Sicherheitsüberprüfung fehlgeschlagen.', 'bs-fabric'));
        }

        $this->entryRepository->deleteAll();

        $this->redirectWithNotice(
            'success',
            __('Alle Einträge wurden gelöscht.', 'bs-fabric'),
            self::SECTION_ADVANCED
        );
    }

    private function handleSchemaExport(): void
    {
        if (! $this->verifyExportNonce()) {
            wp_die(esc_html__('Sicherheitsüberprüfung fehlgeschlagen.', 'bs-fabric'));
        }

        $json = $this->schemaExporter->toJson();
        $filename = 'bs-fabric-schema-' . gmdate('Y-m-d') . '.json';

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download
        echo $json;
        exit;
    }

    private function verifyExportNonce(): bool
    {
        $nonce = isset($_GET['_wpnonce']) ? (string) wp_unslash($_GET['_wpnonce']) : '';

        return wp_verify_nonce($nonce, self::NONCE_ACTION . '_export') !== false;
    }

    private function verifyNonce(string $action): bool
    {
        $nonce = isset($_POST['_wpnonce']) ? (string) wp_unslash($_POST['_wpnonce']) : '';

        return wp_verify_nonce($nonce, $action) !== false;
    }

    private function pageUrl(string $section): string
    {
        return SchemaScreen::url(SchemaScreen::TAB_CONFIG, ['section' => $section]);
    }

    private function redirectWithNotice(string $status, string $message, string $section): void
    {
        wp_safe_redirect(
            add_query_arg(
                [
                    'bs_fabric_notice' => $status,
                    'bs_fabric_message' => rawurlencode($message),
                    'section' => $section,
                ],
                $this->pageUrl($section)
            )
        );
        exit;
    }
}
