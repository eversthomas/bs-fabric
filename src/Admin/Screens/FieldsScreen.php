<?php

declare(strict_types=1);

namespace BsFabric\Admin\Screens;

use BsFabric\Admin\Components\Button;
use BsFabric\Admin\Components\FieldTypeCatalog;
use BsFabric\Admin\Components\FormField;
use BsFabric\Admin\Components\Icon;
use BsFabric\Admin\Components\PageHead;
use BsFabric\Admin\Components\SchemaNotices;
use BsFabric\Admin\Components\TypeBadge;
use BsFabric\Fields\DuplicateFieldNameException;
use BsFabric\Fields\FieldInUseException;
use BsFabric\Fields\FieldNotFoundException;
use BsFabric\Fields\FieldRegistry;
use BsFabric\Fields\FieldRepository;
use BsFabric\Fields\FieldSettings;
use InvalidArgumentException;

final class FieldsScreen
{
    public const LEGACY_SLUG = 'bs-fabric-fields';

    public const NONCE_ACTION = 'bs_fabric_fields';

    public function __construct(
        private readonly FieldRepository $fieldRepository,
        private readonly FieldRegistry $fieldRegistry,
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handleActions']);
    }

    public function handleActions(): void
    {
        if (! $this->isFieldsPageRequest()) {
            return;
        }

        if (! current_user_can('bs_fabric_manage_schema')) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['bs_fabric_action'])
            ? sanitize_key((string) wp_unslash($_POST['bs_fabric_action']))
            : '';

        match ($action) {
            'create' => $this->handleCreate(),
            'update' => $this->handleUpdate(),
            default => null,
        };
    }

    public function render(): void
    {
        if (! current_user_can('bs_fabric_manage_schema')) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'bs-fabric'));
        }

        SchemaNotices::enqueueToastScript();

        $action = isset($_GET['action'])
            ? sanitize_key((string) wp_unslash($_GET['action']))
            : 'list';

        $this->renderList();

        if ($action === 'add') {
            $this->renderFieldSheet(null);
        } elseif ($action === 'edit') {
            $fieldId = isset($_GET['field_id']) ? (int) wp_unslash($_GET['field_id']) : 0;
            $field = $this->fieldRepository->getById($fieldId);

            if (is_array($field)) {
                $this->renderFieldSheet($field);
            }
        }
    }

    private function handleCreate(): void
    {
        if (! $this->verifyNonce(self::NONCE_ACTION . '_create')) {
            wp_die(esc_html__('Sicherheitsüberprüfung fehlgeschlagen.', 'bs-fabric'));
        }

        $name = isset($_POST['name']) ? (string) wp_unslash($_POST['name']) : '';
        $label = isset($_POST['label']) ? (string) wp_unslash($_POST['label']) : '';
        $type = isset($_POST['type']) ? (string) wp_unslash($_POST['type']) : '';

        try {
            $fieldId = $this->fieldRepository->create([
                'name' => $name,
                'label' => $label,
                'type' => $type,
                'settings' => $this->collectSettingsFromPost($type),
            ]);
        } catch (DuplicateFieldNameException) {
            $this->redirectWithNotice('error', __('Ein Feld mit diesem Namen existiert bereits.', 'bs-fabric'), 'add');
        } catch (InvalidArgumentException $exception) {
            $this->redirectWithNotice('error', $exception->getMessage(), 'add');
        }

        $this->redirectWithNotice(
            'success',
            __('Feld wurde angelegt.', 'bs-fabric'),
            'edit',
            $fieldId
        );
    }

    private function handleUpdate(): void
    {
        if (! $this->verifyNonce(self::NONCE_ACTION . '_update')) {
            wp_die(esc_html__('Sicherheitsüberprüfung fehlgeschlagen.', 'bs-fabric'));
        }

        $fieldId = isset($_POST['field_id']) ? (int) wp_unslash($_POST['field_id']) : 0;
        $label = isset($_POST['label']) ? (string) wp_unslash($_POST['label']) : '';

        try {
            $field = $this->fieldRepository->getById($fieldId);

            if ($field === null) {
                throw new FieldNotFoundException();
            }

            $this->fieldRepository->update($fieldId, [
                'label' => $label,
                'settings' => $this->collectSettingsFromPost((string) $field['type']),
            ]);
        } catch (FieldNotFoundException) {
            $this->redirectWithNotice('error', __('Feld nicht gefunden.', 'bs-fabric'));
        } catch (InvalidArgumentException $exception) {
            $this->redirectWithNotice('error', $exception->getMessage(), 'edit', $fieldId);
        }

        $this->redirectWithNotice(
            'success',
            __('Feld wurde gespeichert.', 'bs-fabric'),
            'edit',
            $fieldId
        );
    }

    private function renderList(): void
    {
        $fields = $this->fieldRepository->getAll();
        $types = $this->fieldRegistry->all();

        PageHead::render(
            __('Fields', 'bs-fabric'),
            __('Wiederverwendbare Datenbausteine — die Fäden deines Gewebes.', 'bs-fabric'),
            Button::render(__('Neues Feld', 'bs-fabric'), [
                'variant' => 'primary',
                'icon' => 'plus',
                'href' => $this->pageUrl('add'),
            ])
        );

        if ($fields === []) {
            echo '<div class="bsf-card"><div class="bsf-empty">';
            echo '<h4>' . esc_html__('Noch keine Felder', 'bs-fabric') . '</h4>';
            echo '<p class="bsf-muted">' . esc_html__('Lege deinen ersten Datenbaustein an.', 'bs-fabric') . '</p>';
            echo Button::render(__('Neues Feld', 'bs-fabric'), [
                'variant' => 'primary',
                'icon' => 'plus',
                'href' => $this->pageUrl('add'),
            ]);
            echo '</div></div>';

            return;
        }

        echo '<div class="bsf-card">';
        echo '<div class="bsf-card-head">';
        echo '<div class="bsf-search">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render('search', 16);
        echo '<input type="search" id="bsf-fields-search" placeholder="' . esc_attr__('Felder suchen…', 'bs-fabric') . '" />';
        echo '</div>';
        echo '<div class="bsf-select-wrap" style="width:160px">';
        echo '<select class="bsf-select" id="bsf-fields-type-filter">';
        echo '<option value="">' . esc_html__('Alle Typen', 'bs-fabric') . '</option>';

        foreach ($types as $type) {
            printf(
                '<option value="%s">%s</option>',
                esc_attr($type->getType()),
                esc_html($type->getLabel())
            );
        }

        echo '</select>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render('chevd', 15);
        echo '</div></div>';

        echo '<div id="bsf-fields-table-root">';
        echo '<table class="bsf-tbl">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Bezeichnung', 'bs-fabric') . '</th>';
        echo '<th>' . esc_html__('Field-Key', 'bs-fabric') . '</th>';
        echo '<th>' . esc_html__('Typ', 'bs-fabric') . '</th>';
        echo '<th>' . esc_html__('Verwendung', 'bs-fabric') . '</th>';
        echo '<th style="width:120px"></th>';
        echo '</tr></thead><tbody>';

        foreach ($fields as $field) {
            $usage = $this->fieldRepository->countTemplateUsage((int) $field['id']);

            echo '<tr data-bsf-field-row data-label="' . esc_attr($field['label']) . '" data-name="' . esc_attr($field['name']) . '" data-type="' . esc_attr($field['type']) . '">';
            echo '<td><span class="bsf-cell-strong">' . esc_html($field['label']) . '</span></td>';
            echo '<td><span class="bsf-key">' . esc_html($field['name']) . '</span></td>';
            echo '<td>';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo TypeBadge::render($field['type'], $this->fieldRegistry);
            echo '</td>';
            echo '<td class="bsf-cell-mute">';
            printf(
                esc_html(_n('%d Template', '%d Templates', $usage, 'bs-fabric')),
                $usage
            );
            echo '</td>';
            echo '<td><div class="bsf-row-actions">';
            echo Button::render(__('Bearbeiten', 'bs-fabric'), [
                'variant' => 'subtle',
                'size' => 'sm',
                'icon' => 'edit',
                'href' => $this->pageUrl('edit', (int) $field['id']),
            ]);
            echo '<button type="button" class="bsf-btn bsf-btn-subtle bsf-btn-sm bs-fabric-delete-field" data-field-id="' . esc_attr((string) $field['id']) . '" data-field-name="' . esc_attr($field['name']) . '" title="' . esc_attr__('Löschen', 'bs-fabric') . '">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo Icon::render('trash', 15);
            echo '</button>';
            echo '</div></td>';
            echo '</tr>';
        }

        echo '</tbody></table></div></div>';
    }

    /**
     * @param array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>
     * }|null $field
     */
    private function renderFieldSheet(?array $field): void
    {
        $isNew = $field === null;

        $fieldId = $isNew ? 0 : (int) $field['id'];
        $type = $isNew ? 'text' : (string) $field['type'];
        $label = $isNew ? '' : (string) $field['label'];
        $name = $isNew ? '' : (string) $field['name'];
        $settings = $isNew ? [] : FieldSettings::mergeGlobal($field['settings']);

        $formAction = $isNew ? $this->pageUrl('add') : $this->pageUrl('edit', $fieldId);
        $listUrl = $this->pageUrl();

        echo '<div class="bsf-scrim" data-bsf-field-scrim data-bsf-sheet-close></div>';
        printf(
            '<aside class="bsf-sheet" id="bsf-field-sheet" data-list-url="%s" data-is-new="%s" role="dialog" aria-modal="true">',
            esc_url($listUrl),
            $isNew ? '1' : '0'
        );

        echo '<div class="bsf-sheet-head">';
        echo '<div class="bsf-col bsf-grow">';
        echo '<h2>' . esc_html($isNew ? __('Neues Feld', 'bs-fabric') : __('Feld bearbeiten', 'bs-fabric')) . '</h2>';

        if (! $isNew) {
            echo '<span class="bsf-muted" style="font-size:12.5px">';
            printf(
                esc_html__('Feld-ID %d', 'bs-fabric'),
                $fieldId
            );
            echo '</span>';
        } else {
            echo '<span class="bsf-muted" style="font-size:12.5px">' . esc_html__('Definiere einen wiederverwendbaren Datenbaustein', 'bs-fabric') . '</span>';
        }

        echo '</div>';
        echo '<button type="button" class="bsf-btn bsf-btn-subtle bsf-btn-sm" data-bsf-sheet-close aria-label="' . esc_attr__('Schließen', 'bs-fabric') . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render('x', 16);
        echo '</button></div>';

        echo '<form method="post" action="' . esc_url($formAction) . '">';
        wp_nonce_field($isNew ? self::NONCE_ACTION . '_create' : self::NONCE_ACTION . '_update');
        echo '<input type="hidden" name="bs_fabric_action" value="' . esc_attr($isNew ? 'create' : 'update') . '" />';

        if (! $isNew) {
            echo '<input type="hidden" name="field_id" value="' . esc_attr((string) $fieldId) . '" />';
        }

        echo '<div class="bsf-sheet-body">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo FormField::render(
            __('Bezeichnung', 'bs-fabric'),
            sprintf(
                '<input class="bsf-input" name="label" data-bsf-field-label type="text" value="%s" required autofocus placeholder="%s" />',
                esc_attr($label),
                esc_attr__('z. B. Titelbild', 'bs-fabric')
            ),
            __('So sehen Redakteure das Feld in der Eingabemaske.', 'bs-fabric'),
            true
        );

        if ($isNew) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo FormField::render(
                __('Feld-Key', 'bs-fabric'),
                sprintf(
                    '<input class="bsf-input bsf-input-mono" name="name" data-bsf-field-name type="text" value="%s" required placeholder="%s" />',
                    esc_attr($name),
                    esc_attr__('titelbild', 'bs-fabric')
                ),
                __('Technischer Bezeichner für Templates & API. Nur Kleinbuchstaben.', 'bs-fabric'),
                true
            );
        } else {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo FormField::render(
                __('Feld-Key', 'bs-fabric'),
                '<span class="bsf-key">' . esc_html($name) . '</span>',
                __('Nach Anlage nicht mehr änderbar.', 'bs-fabric')
            );
        }

        echo '<div class="bsf-field">';
        echo '<span class="bsf-flabel">' . esc_html__('Feldtyp', 'bs-fabric') . ' <span class="bsf-req">*</span></span>';
        $this->renderTypePicker($type, ! $isNew);
        echo '</div>';

        if ($isNew) {
            echo '<input type="hidden" name="type" data-bsf-field-type value="' . esc_attr($type) . '" />';
        }

        if (! $isNew) {
            echo '<hr class="bsf-divider" />';

            echo '<div class="bsf-switch-row">';
            echo '<div>';
            echo '<div class="bsf-flabel" style="margin:0">' . esc_html__('In Listenansicht', 'bs-fabric') . '</div>';
            echo '<p class="bsf-fhint" style="margin-top:4px">' . esc_html__('Sortierbare Spalte in Entry-Listen.', 'bs-fabric') . '</p>';
            echo '</div>';
            echo '<input type="checkbox" class="bsf-hidden-input" name="show_in_list" id="bsf-show-in-list" value="1"';
            checked(FieldSettings::showInList($settings));
            echo ' />';
            echo '<button type="button" class="bsf-switch" data-for="bsf-show-in-list" aria-pressed="false"></button>';
            echo '</div>';

            echo '<hr class="bsf-divider" />';
            echo '<div class="bsf-settings-panel">';
            echo '<div class="bsf-flabel" style="margin-bottom:10px">' . esc_html__('Typ-Einstellungen', 'bs-fabric') . '</div>';
            $fieldType = $this->fieldRegistry->get($type);
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $fieldType->renderSettings($field);
            echo '</div>';
        }

        echo '</div>';

        echo '<div class="bsf-sheet-foot">';
        echo Button::render($isNew ? __('Feld anlegen', 'bs-fabric') : __('Speichern', 'bs-fabric'), [
            'variant' => 'primary',
            'icon' => 'save',
            'type' => 'submit',
        ]);
        echo '<button type="button" class="bsf-btn bsf-btn-subtle" data-bsf-sheet-close>' . esc_html__('Abbrechen', 'bs-fabric') . '</button>';
        echo '<span class="bsf-grow"></span>';

        if (! $isNew) {
            echo '<button type="button" class="bsf-btn bsf-btn-subtle bsf-btn-sm bs-fabric-delete-field" data-field-id="' . esc_attr((string) $fieldId) . '" data-field-name="' . esc_attr($name) . '">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo Icon::render('trash', 15);
            echo esc_html__('Löschen', 'bs-fabric') . '</button>';
        }

        echo '</div></form></aside>';
    }

    private function renderTypePicker(string $selected, bool $disabled): void
    {
        $groups = FieldTypeCatalog::grouped($this->fieldRegistry);

        foreach ($groups as $groupLabel => $items) {
            echo '<div class="bsf-type-group-label">' . esc_html($groupLabel) . '</div>';
            echo '<div class="bsf-type-grid">';

            foreach ($items as $item) {
                $isSelected = $item['type'] === $selected;
                printf(
                    '<button type="button" class="bsf-type-option%s" data-type="%s"%s>',
                    $isSelected ? ' is-selected' : '',
                    esc_attr($item['type']),
                    $disabled ? ' disabled' : ''
                );
                echo '<span class="bsf-type-option-icon">';
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo Icon::render($item['icon'], 17);
                echo '</span><span class="bsf-col bsf-grow">';
                echo '<span class="bsf-type-option-title">' . esc_html($item['label']) . '</span>';
                echo '<span class="bsf-type-option-hint">' . esc_html($item['hint']) . '</span>';
                echo '</span></button>';
            }

            echo '</div>';
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function collectSettingsFromPost(string $type): array
    {
        $fieldId = isset($_POST['field_id']) ? (int) wp_unslash($_POST['field_id']) : 0;
        $defaults = FieldSettings::mergeGlobal(
            $this->fieldRegistry->get($type)->getDefaultSettings()
        );

        if (! isset($_POST['bs_fabric_field_settings']) || ! is_array($_POST['bs_fabric_field_settings'])) {
            $settings = $fieldId > 0
                ? $this->mergeGlobalSettingsFromPost($defaults)
                : $defaults;

            return $this->fieldRegistry->get($type)->sanitizeSettings($settings);
        }

        $rawSettings = wp_unslash($_POST['bs_fabric_field_settings']);
        $field = $this->fieldRepository->getById($fieldId);
        $fieldName = is_array($field) ? (string) $field['name'] : sanitize_key($type);

        if (! isset($rawSettings[$fieldName]) || ! is_array($rawSettings[$fieldName])) {
            $settings = $fieldId > 0
                ? $this->mergeGlobalSettingsFromPost($defaults)
                : $defaults;

            return $this->fieldRegistry->get($type)->sanitizeSettings($settings);
        }

        $settings = $this->mergeGlobalSettingsFromPost(array_merge($defaults, $rawSettings[$fieldName]));

        return $this->fieldRegistry->get($type)->sanitizeSettings($settings);
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private function mergeGlobalSettingsFromPost(array $settings): array
    {
        $settings[FieldSettings::SHOW_IN_LIST] = isset($_POST['show_in_list']);

        return $settings;
    }

    private function isFieldsPageRequest(): bool
    {
        return SchemaScreen::isTab(SchemaScreen::TAB_FIELDS);
    }

    private function verifyNonce(string $action): bool
    {
        $nonce = isset($_POST['_wpnonce']) ? (string) wp_unslash($_POST['_wpnonce']) : '';

        return wp_verify_nonce($nonce, $action) !== false;
    }

    private function pageUrl(string $action = 'list', int $fieldId = 0): string
    {
        $args = [];

        if ($action !== 'list') {
            $args['action'] = $action;
        }

        if ($fieldId > 0) {
            $args['field_id'] = $fieldId;
        }

        return SchemaScreen::url(SchemaScreen::TAB_FIELDS, $args);
    }

    private function redirectWithNotice(string $status, string $message, string $action = 'list', int $fieldId = 0): void
    {
        $url = add_query_arg(
            [
                'bs_fabric_notice' => $status,
                'bs_fabric_message' => rawurlencode($message),
            ] + ($action !== 'list' ? ['action' => $action] : [])
            + ($fieldId > 0 ? ['field_id' => $fieldId] : []),
            SchemaScreen::url(SchemaScreen::TAB_FIELDS)
        );

        wp_safe_redirect($url);
        exit;
    }
}
