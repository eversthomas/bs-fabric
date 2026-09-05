<?php

declare(strict_types=1);

namespace BsFabric\Admin\Screens;

use BsFabric\Admin\Components\Button;
use BsFabric\Admin\Components\FieldTypeCatalog;
use BsFabric\Admin\Components\FormField;
use BsFabric\Admin\Components\Icon;
use BsFabric\Admin\Components\PageHead;
use BsFabric\Admin\Components\SchemaNotices;
use BsFabric\Admin\DocsExampleRenderer;
use BsFabric\Core\TemplateAuthorization;
use BsFabric\Entries\EntryRepository;
use BsFabric\Fields\FieldRepository;
use BsFabric\Templates\DuplicateTemplateNameException;
use BsFabric\Templates\Template;
use BsFabric\Templates\TemplateCodeSnippets;
use BsFabric\Templates\TemplateNotFoundException;
use BsFabric\Templates\TemplateRepository;
use BsFabric\Templates\TemplateSettings;
use InvalidArgumentException;
use RuntimeException;

final class TemplatesScreen
{
    public const LEGACY_SLUG = 'bs-fabric-templates';

    public const NONCE_ACTION = 'bs_fabric_templates';

    public function __construct(
        private readonly TemplateRepository $templateRepository,
        private readonly FieldRepository $fieldRepository,
        private readonly EntryRepository $entryRepository,
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handleActions']);
    }

    public function handleActions(): void
    {
        if (! $this->isTemplatesPageRequest()) {
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
        SchemaNotices::enqueueToastScript();

        $action = isset($_GET['action'])
            ? sanitize_key((string) wp_unslash($_GET['action']))
            : 'list';

        if (TemplateAuthorization::canManageSchema()) {
            match ($action) {
                'add' => $this->renderList(true),
                'edit' => $this->renderEditForm(false),
                default => $this->renderList(false),
            };

            return;
        }

        if ($action === 'edit') {
            $templateId = isset($_GET['template_id']) ? (int) wp_unslash($_GET['template_id']) : 0;
            $template = $templateId > 0 ? $this->templateRepository->getById($templateId) : null;

            if (TemplateAuthorization::canManageTemplateFields($template)) {
                $this->renderEditForm(true);

                return;
            }
        }

        wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'bs-fabric'));
    }

    private function handleCreate(): void
    {
        if (! $this->verifyNonce(self::NONCE_ACTION . '_create')) {
            wp_die(esc_html__('Sicherheitsüberprüfung fehlgeschlagen.', 'bs-fabric'));
        }

        $name = isset($_POST['name']) ? (string) wp_unslash($_POST['name']) : '';
        $label = isset($_POST['label']) ? (string) wp_unslash($_POST['label']) : '';

        try {
            $templateId = $this->templateRepository->create([
                'name' => $name,
                'label' => $label,
            ]);
        } catch (DuplicateTemplateNameException) {
            $this->redirectWithNotice('error', __('Ein Template mit diesem Namen existiert bereits.', 'bs-fabric'), 'add');
        } catch (InvalidArgumentException $exception) {
            $this->redirectWithNotice('error', $exception->getMessage(), 'add');
        } catch (RuntimeException $exception) {
            $this->redirectWithNotice('error', $exception->getMessage(), 'add');
        }

        $this->redirectWithNotice(
            'success',
            __('Template wurde angelegt.', 'bs-fabric'),
            'edit',
            $templateId
        );
    }

    private function handleUpdate(): void
    {
        if (! $this->verifyNonce(self::NONCE_ACTION . '_update')) {
            wp_die(esc_html__('Sicherheitsüberprüfung fehlgeschlagen.', 'bs-fabric'));
        }

        $templateId = isset($_POST['template_id']) ? (int) wp_unslash($_POST['template_id']) : 0;
        $label = isset($_POST['label']) ? (string) wp_unslash($_POST['label']) : '';
        $titleField = isset($_POST['title_field']) ? (string) wp_unslash($_POST['title_field']) : '';

        try {
            $template = $this->templateRepository->getById($templateId);

            if ($template === null) {
                throw new TemplateNotFoundException();
            }

            $previousTitleField = $template->title_field;
            $settings = TemplateSettings::mergeGlobal($template->settings);
            $settings[TemplateSettings::SHOW_IN_MENU] = isset($_POST['show_in_menu']);
            $settings[TemplateSettings::EDITORS_MAY_MANAGE_FIELDS] = isset($_POST['editors_may_manage_fields']);
            $settings[TemplateSettings::REST_VISIBLE] = isset($_POST['rest_visible']);

            $this->templateRepository->update($templateId, [
                'label' => $label,
                'title_field' => $titleField !== '' ? $titleField : null,
                'settings' => $settings,
            ]);

            if ($titleField !== ($previousTitleField ?? '')) {
                $this->entryRepository->resyncTitlesForTemplate($templateId);
            }
        } catch (TemplateNotFoundException) {
            $this->redirectWithNotice('error', __('Template nicht gefunden.', 'bs-fabric'));
        } catch (InvalidArgumentException $exception) {
            $this->redirectWithNotice('error', $exception->getMessage(), 'edit', $templateId);
        } catch (RuntimeException $exception) {
            $this->redirectWithNotice('error', $exception->getMessage(), 'edit', $templateId);
        }

        $this->redirectWithNotice(
            'success',
            __('Template wurde gespeichert.', 'bs-fabric'),
            'edit',
            $templateId
        );
    }

    private function renderList(bool $openAddSheet): void
    {
        $templates = $this->templateRepository->getAll();

        PageHead::render(
            __('Templates', 'bs-fabric'),
            __('Webmuster für strukturierte Inhalte — Blaupausen für Redakteure.', 'bs-fabric'),
            Button::render(__('Neues Template', 'bs-fabric'), [
                'variant' => 'primary',
                'icon' => 'plus',
                'href' => $this->pageUrl('add'),
            ])
        );

        echo '<div class="bsf-template-grid">';

        foreach ($templates as $index => $template) {
            $this->renderTemplateCard($template, $index);
        }

        echo '<a href="' . esc_url($this->pageUrl('add')) . '" class="bsf-card bsf-template-card bsf-template-card-add">';
        echo '<div class="bsf-col" style="align-items:center;gap:8px">';
        echo '<span style="width:38px;height:38px;border-radius:10px;display:grid;place-items:center;background:var(--surface);border:1px solid var(--border)">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render('plus', 20);
        echo '</span>';
        echo '<span style="font-weight:600;font-size:13.5px">' . esc_html__('Template anlegen', 'bs-fabric') . '</span>';
        echo '</div></a>';

        echo '</div>';

        if ($openAddSheet) {
            $this->renderAddSheet();
        }
    }

    private function renderTemplateCard(Template $template, int $index): void
    {
        $fieldCount = count($this->templateRepository->getFields($template->getId()));
        $entryCount = $this->entryRepository->getByTemplate($template->getId())->count();
        $toneClass = $index % 2 === 0 ? 'bsf-template-icon-accent' : 'bsf-template-icon-thread';
        $editUrl = $this->pageUrl('edit', $template->getId());

        echo '<div class="bsf-card bsf-template-card-wrap" style="padding:0;position:relative">';
        echo '<a href="' . esc_url($editUrl) . '" class="bsf-card bsf-template-card" style="box-shadow:none;border:none">';
        echo '<div class="bsf-template-card-head">';
        echo '<span class="bsf-template-icon ' . esc_attr($toneClass) . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render('box', 22);
        echo '</span>';
        echo '<span class="bsf-col bsf-grow">';
        echo '<span class="bsf-template-card-title">' . esc_html($template->label) . '</span>';
        echo '<span class="bsf-key">' . esc_html($template->name) . '</span>';
        echo '</span>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render('chevr', 16);
        echo '</div>';
        echo '<p class="bsf-template-card-desc">';
        echo esc_html(
            TemplateSettings::showInMenu($template->settings)
                ? __('Im Fabric-Menü sichtbar.', 'bs-fabric')
                : __('Nur in Einstellungen sichtbar.', 'bs-fabric')
        );
        echo '</p>';
        echo '<div class="bsf-template-card-foot">';
        echo '<span class="bsf-row"><span class="bsf-muted">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render('threads', 14);
        echo '</span><strong>' . esc_html((string) $fieldCount) . '</strong> <span class="bsf-muted">' . esc_html__('Felder', 'bs-fabric') . '</span></span>';
        echo '<span class="bsf-row"><span class="bsf-muted">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render('rows', 14);
        echo '</span><strong>' . esc_html((string) $entryCount) . '</strong> <span class="bsf-muted">' . esc_html__('Einträge', 'bs-fabric') . '</span></span>';
        echo '</div></a>';
        echo '<button type="button" class="bsf-btn bsf-btn-subtle bsf-btn-sm bs-fabric-delete-template" style="position:absolute;top:12px;right:12px" data-template-id="' . esc_attr((string) $template->getId()) . '" title="' . esc_attr__('Löschen', 'bs-fabric') . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render('trash', 15);
        echo '</button></div>';
    }

    private function renderAddSheet(): void
    {
        $listUrl = $this->pageUrl();

        echo '<div class="bsf-scrim is-open" data-bsf-template-scrim data-bsf-sheet-close></div>';
        printf(
            '<aside class="bsf-sheet is-open" id="bsf-template-sheet" data-list-url="%s" role="dialog" aria-modal="true">',
            esc_url($listUrl)
        );

        echo '<div class="bsf-sheet-head">';
        echo '<div class="bsf-col bsf-grow">';
        echo '<h2>' . esc_html__('Neues Template', 'bs-fabric') . '</h2>';
        echo '<span class="bsf-muted" style="font-size:12.5px">' . esc_html__('Blaupause für strukturierte Einträge', 'bs-fabric') . '</span>';
        echo '</div>';
        echo '<button type="button" class="bsf-btn bsf-btn-subtle bsf-btn-sm" data-bsf-sheet-close aria-label="' . esc_attr__('Schließen', 'bs-fabric') . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render('x', 16);
        echo '</button></div>';

        echo '<form method="post" action="' . esc_url($this->pageUrl('add')) . '">';
        wp_nonce_field(self::NONCE_ACTION . '_create');
        echo '<input type="hidden" name="bs_fabric_action" value="create" />';
        echo '<div class="bsf-sheet-body">';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo FormField::render(
            __('Bezeichnung', 'bs-fabric'),
            '<input class="bsf-input" name="label" data-bsf-template-label type="text" required autofocus placeholder="' . esc_attr__('z. B. Produkt', 'bs-fabric') . '" />',
            __('Anzeigename für Redakteure.', 'bs-fabric'),
            true
        );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo FormField::render(
            __('Template-Key', 'bs-fabric'),
            '<input class="bsf-input bsf-input-mono" name="name" data-bsf-template-name type="text" required placeholder="' . esc_attr__('produkt', 'bs-fabric') . '" />',
            __('Technischer Bezeichner. Nach Anlage nicht änderbar.', 'bs-fabric'),
            true
        );

        echo '</div><div class="bsf-sheet-foot">';
        echo Button::render(__('Template anlegen', 'bs-fabric'), [
            'variant' => 'primary',
            'icon' => 'plus',
            'type' => 'submit',
        ]);
        echo '<button type="button" class="bsf-btn bsf-btn-subtle" data-bsf-sheet-close>' . esc_html__('Abbrechen', 'bs-fabric') . '</button>';
        echo '</div></form></aside>';
    }

    private function renderEditForm(bool $editorMode): void
    {
        $templateId = isset($_GET['template_id']) ? (int) wp_unslash($_GET['template_id']) : 0;
        $template = $this->templateRepository->getById($templateId);

        if ($template === null) {
            PageHead::render(__('Template bearbeiten', 'bs-fabric'));
            echo '<div class="bsf-card bsf-card-body"><p class="bsf-muted">' . esc_html__('Template nicht gefunden.', 'bs-fabric') . '</p></div>';

            return;
        }

        if ($editorMode && ! TemplateAuthorization::canManageTemplateFields($template)) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'bs-fabric'));
        }

        $assignedFields = $this->templateRepository->getFields($templateId);
        $allFields = $this->fieldRepository->getAll();
        $assignedFieldIds = array_column($assignedFields, 'field_id');
        $availableFields = array_values(array_filter(
            $allFields,
            static fn (array $field): bool => ! in_array($field['id'], $assignedFieldIds, true)
        ));

        $backHref = $editorMode
            ? EntryListScreen::urlForTemplate($template)
            : $this->pageUrl();

        $headActions = $editorMode
            ? Button::render(__('Zurück zur Liste', 'bs-fabric'), [
                'variant' => 'ghost',
                'href' => $backHref,
            ])
            : Button::render(__('Speichern', 'bs-fabric'), [
                'variant' => 'primary',
                'icon' => 'save',
                'type' => 'submit',
                'form' => 'bsf-template-settings-form',
            ]) . Button::render(__('Zurück', 'bs-fabric'), [
                'variant' => 'ghost',
                'href' => $backHref,
            ]);

        PageHead::render(
            $editorMode ? __('Felder verwalten', 'bs-fabric') : $template->label,
            $editorMode
                ? sprintf(
                    /* translators: %s: template label */
                    __('Felder für „%s“ aus der Bibliothek zuweisen.', 'bs-fabric'),
                    $template->label
                )
                : sprintf(
                    /* translators: %s: template key */
                    __('Template-Key: %s', 'bs-fabric'),
                    $template->name
                ),
            $headActions
        );

        echo '<div class="bsf-builder bs-fabric-template-edit" data-template-id="' . esc_attr((string) $templateId) . '">';

        echo '<div class="bsf-col" style="gap:var(--gap)">';

        if ($availableFields !== []) {
            echo '<div class="bsf-add-field-wrap">';
        }

        echo '<div class="bsf-card" style="position:relative">';
        echo '<div class="bsf-card-head">';
        echo '<h3>' . esc_html__('Felder im Template', 'bs-fabric') . '</h3>';
        echo '<div class="bsf-grow"></div>';

        if ($availableFields !== []) {
            echo '<button type="button" class="bsf-btn bsf-btn-primary bsf-btn-sm" data-bsf-add-field-toggle>';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo Icon::render('plus', 15);
            echo esc_html__('Feld hinzufügen', 'bs-fabric') . '</button>';
        }

        echo '</div>';

        echo '<ul id="bs-fabric-template-fields" class="bsf-builder-rows">';

        foreach ($assignedFields as $field) {
            $this->renderAssignedFieldItem($field);
        }

        echo '</ul>';

        if ($assignedFields === []) {
            echo '<div class="bsf-empty" style="padding:24px">';
            echo '<p class="bsf-muted">' . esc_html__('Noch keine Felder zugewiesen.', 'bs-fabric') . '</p>';
            echo '</div>';
        }

        echo '</div>';

        if ($availableFields !== []) {
            $this->renderAddFieldMenu($availableFields, $editorMode);
            echo '<select id="bsf-available-field-hidden" class="bsf-hidden-input bs-fabric-available-field" aria-hidden="true" tabindex="-1">';

            foreach ($availableFields as $field) {
                printf(
                    '<option value="%d">%s</option>',
                    (int) $field['id'],
                    esc_attr($field['label'])
                );
            }

            echo '</select>';
            echo '<button type="button" class="bsf-hidden-input bs-fabric-assign-field" aria-hidden="true" tabindex="-1"></button>';
            echo '</div>';
        }

        if (! $editorMode) {
            echo '<div class="bsf-code-examples">';
            $this->renderCodeExamples($template, $assignedFields);
            echo '</div>';
        }

        echo '</div>';

        if (! $editorMode) {
            $this->renderTemplateSettingsAside($template, $templateId, $assignedFields);
        }

        echo '</div>';
    }

    /**
     * @param list<array{
     *     name: string,
     *     label: string,
     *     type: string
     * }> $assignedFields
     */
    private function renderTemplateSettingsAside(Template $template, int $templateId, array $assignedFields): void
    {
        echo '<aside class="bsf-col" style="gap:var(--gap)">';
        echo '<div class="bsf-card">';
        echo '<div class="bsf-card-head"><h3>' . esc_html__('Einstellungen', 'bs-fabric') . '</h3></div>';
        echo '<div class="bsf-card-body">';
        echo '<form method="post" action="' . esc_url($this->pageUrl('edit', $templateId)) . '" id="bsf-template-settings-form">';
        wp_nonce_field(self::NONCE_ACTION . '_update');
        echo '<input type="hidden" name="bs_fabric_action" value="update" />';
        echo '<input type="hidden" name="template_id" value="' . esc_attr((string) $templateId) . '" />';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo FormField::render(
            __('Bezeichnung', 'bs-fabric'),
            sprintf(
                '<input class="bsf-input" name="label" id="bs-fabric-template-label" type="text" value="%s" required />',
                esc_attr($template->label)
            ),
            '',
            true
        );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo FormField::render(
            __('Template-Key', 'bs-fabric'),
            '<span class="bsf-key">' . esc_html($template->name) . '</span>'
        );

        $titleFieldSelect = '<div class="bsf-select-wrap"><select class="bsf-select" name="title_field" id="bs-fabric-title-field">';
        $titleFieldSelect .= '<option value="">' . esc_html__('— Keins —', 'bs-fabric') . '</option>';

        foreach ($assignedFields as $field) {
            $titleFieldSelect .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr($field['name']),
                selected($template->title_field, $field['name'], false),
                esc_html($field['label'] . ' (' . $field['name'] . ')')
            );
        }

        $titleFieldSelect .= '</select>' . Icon::render('chevd', 15) . '</div>';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo FormField::render(
            __('Titel-Feld', 'bs-fabric'),
            $titleFieldSelect,
            __('Welches zugewiesene Feld als Entry-Titel dient.', 'bs-fabric')
        );

        $templateSettings = TemplateSettings::mergeGlobal($template->settings);
        echo '<div class="bsf-switch-row">';
        echo '<div><span class="bsf-flabel" style="margin:0">' . esc_html__('Im Menü anzeigen', 'bs-fabric') . '</span>';
        echo '<p class="bsf-fhint" style="margin-top:4px">' . esc_html__('Eigener Fabric-Menüpunkt für Entries.', 'bs-fabric') . '</p></div>';
        echo '<input type="checkbox" class="bsf-hidden-input" name="show_in_menu" id="bs-fabric-show-in-menu" value="1"';
        checked(TemplateSettings::showInMenu($templateSettings));
        echo ' />';
        echo '<button type="button" class="bsf-switch" data-for="bs-fabric-show-in-menu"></button>';
        echo '</div>';

        echo '<div class="bsf-switch-row" style="margin-top:14px">';
        echo '<div><span class="bsf-flabel" style="margin:0">' . esc_html__('Redakteure dürfen Felder zuweisen', 'bs-fabric') . '</span>';
        echo '<p class="bsf-fhint" style="margin-top:4px">' . esc_html__('Redakteure können in dieser Liste Felder aus der Bibliothek hinzufügen und entfernen.', 'bs-fabric') . '</p></div>';
        echo '<input type="checkbox" class="bsf-hidden-input" name="editors_may_manage_fields" id="bs-fabric-editors-manage-fields" value="1"';
        checked(TemplateSettings::editorsMayManageFields($templateSettings));
        echo ' />';
        echo '<button type="button" class="bsf-switch" data-for="bs-fabric-editors-manage-fields"></button>';
        echo '</div>';

        echo '<div class="bsf-switch-row" style="margin-top:14px">';
        echo '<div><span class="bsf-flabel" style="margin:0">' . esc_html__('In REST-API sichtbar', 'bs-fabric') . '</span>';
        echo '<p class="bsf-fhint" style="margin-top:4px">' . esc_html__('Wenn deaktiviert, liefert die REST-API für dieses Template grundsätzlich nichts zurück — unabhängig vom Entry-Status. Einzelne Felder lassen sich zusätzlich pro Feld ausblenden.', 'bs-fabric') . '</p></div>';
        echo '<input type="checkbox" class="bsf-hidden-input" name="rest_visible" id="bs-fabric-rest-visible" value="1"';
        checked(TemplateSettings::restVisible($templateSettings));
        echo ' />';
        echo '<button type="button" class="bsf-switch" data-for="bs-fabric-rest-visible"></button>';
        echo '</div>';

        echo '<div style="margin-top:18px">';
        echo Button::render(__('Speichern', 'bs-fabric'), [
            'variant' => 'primary',
            'icon' => 'save',
            'type' => 'submit',
        ]);
        echo '</div></form></div></div></aside></div>';
    }

    /**
     * @param list<array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string
     * }> $availableFields
     */
    private function renderAddFieldMenu(array $availableFields, bool $editorMode): void
    {
        echo '<div class="bsf-add-field-menu" hidden>';
        echo '<div class="bsf-add-field-search">';
        echo '<div class="bsf-search" style="width:100%">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render('search', 15);
        echo '<input type="search" data-bsf-add-field-search placeholder="' . esc_attr__('Feld aus Bibliothek…', 'bs-fabric') . '" />';
        echo '</div></div>';
        echo '<div class="bsf-add-field-list">';

        foreach ($availableFields as $field) {
            printf(
                '<button type="button" class="bsf-add-field-item" data-bsf-add-field-item data-field-id="%d" data-label="%s" data-name="%s">',
                (int) $field['id'],
                esc_attr($field['label']),
                esc_attr($field['name'])
            );
            echo '<span class="bsf-builder-row-icon">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo Icon::render(FieldTypeCatalog::iconFor($field['type']), 15);
            echo '</span>';
            echo '<span class="bsf-col bsf-grow">';
            echo '<span class="bsf-cell-strong" style="font-size:13px">' . esc_html($field['label']) . '</span>';
            echo '<span class="bsf-key" style="border:none;background:none;padding:0">' . esc_html($field['name']) . '</span>';
            echo '</span>';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo Icon::render('plus', 15);
            echo '</button>';
        }

        echo '</div>';
        echo '<div style="border-top:1px solid var(--border);padding:6px">';

        if (! $editorMode) {
            echo '<a href="' . esc_url(SchemaScreen::url(SchemaScreen::TAB_FIELDS, ['action' => 'add'])) . '" class="bsf-add-field-item" style="color:var(--accent-ink);font-weight:600">';
            echo '<span class="bsf-builder-row-icon" style="background:var(--accent-soft)">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo Icon::render('bolt', 15);
            echo '</span>';
            echo esc_html__('Neues Feld erstellen…', 'bs-fabric');
            echo '</a>';
        }

        echo '</div></div>';
    }

    /**
     * @param list<array{
     *     name: string,
     *     label: string,
     *     type: string
     * }> $assignedFields
     */
    private function renderCodeExamples(Template $template, array $assignedFields): void
    {
        echo '<div class="bsf-card">';
        echo '<div class="bsf-card-head"><h3>' . esc_html__('Code-Beispiele', 'bs-fabric') . '</h3></div>';
        echo '<div class="bsf-card-body">';

        if ($assignedFields === []) {
            echo '<p class="bsf-muted">' . esc_html__(
                'Weise diesem Template zuerst Felder zu — dann erscheinen hier passende Beispiele.',
                'bs-fabric'
            ) . '</p>';
        } else {
            DocsExampleRenderer::renderExamples(
                TemplateCodeSnippets::build(
                    (string) $template->name,
                    (string) $template->label,
                    $assignedFields
                )
            );
        }

        echo '</div></div>';
    }

    /**
     * @param array{
     *     assignment_id: int,
     *     field_id: int,
     *     sort_order: int,
     *     is_required: bool,
     *     rest_hidden: bool,
     *     name: string,
     *     label: string,
     *     type: string
     * } $field
     */
    private function renderAssignedFieldItem(array $field): void
    {
        echo '<li class="bsf-builder-row bs-fabric-template-field-item" data-field-id="' . esc_attr((string) $field['field_id']) . '">';
        echo '<span class="bsf-builder-row-handle bs-fabric-template-field-handle" aria-hidden="true">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render('drag', 18);
        echo '</span>';
        echo '<span class="bsf-builder-row-icon">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render(FieldTypeCatalog::iconFor($field['type']), 17);
        echo '</span>';
        echo '<span class="bsf-col bsf-grow">';
        echo '<span class="bsf-cell-strong">' . esc_html($field['label']) . '</span>';
        echo '<span class="bsf-row" style="gap:8px;margin-top:2px">';
        echo '<span class="bsf-key" style="font-size:11px">' . esc_html($field['name']) . '</span>';
        echo '<span class="bsf-muted" style="font-size:12px">' . esc_html($field['type']) . '</span>';
        echo '</span></span>';
        echo '<label class="bsf-row bsf-muted" style="font-size:12px;gap:6px">';
        echo '<input type="checkbox" class="bs-fabric-field-required-toggle" data-field-id="' . esc_attr((string) $field['field_id']) . '"';
        checked($field['is_required'], true);
        echo ' /> ' . esc_html__('Pflicht', 'bs-fabric');
        echo '</label>';
        echo '<label class="bsf-row bsf-muted" style="font-size:12px;gap:6px" title="' . esc_attr__('Dieses Feld nicht in der REST-API ausliefern, auch wenn das Template REST-sichtbar ist.', 'bs-fabric') . '">';
        echo '<input type="checkbox" class="bs-fabric-field-rest-hidden-toggle" data-field-id="' . esc_attr((string) $field['field_id']) . '"';
        checked($field['rest_hidden'], true);
        echo ' /> ' . esc_html__('REST verstecken', 'bs-fabric');
        echo '</label>';
        echo '<div class="bsf-builder-row-actions">';
        echo '<button type="button" class="bsf-btn bsf-btn-subtle bsf-btn-sm bs-fabric-remove-template-field" data-field-id="' . esc_attr((string) $field['field_id']) . '" title="' . esc_attr__('Entfernen', 'bs-fabric') . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render('trash', 15);
        echo '</button></div></li>';
    }

    private function isTemplatesPageRequest(): bool
    {
        return SchemaScreen::isTab(SchemaScreen::TAB_TEMPLATES);
    }

    private function verifyNonce(string $action): bool
    {
        $nonce = isset($_POST['_wpnonce']) ? (string) wp_unslash($_POST['_wpnonce']) : '';

        return wp_verify_nonce($nonce, $action) !== false;
    }

    private function pageUrl(string $action = 'list', int $templateId = 0): string
    {
        $args = [];

        if ($action !== 'list') {
            $args['action'] = $action;
        }

        if ($templateId > 0) {
            $args['template_id'] = $templateId;
        }

        return SchemaScreen::url(SchemaScreen::TAB_TEMPLATES, $args);
    }

    private function redirectWithNotice(string $status, string $message, string $action = 'list', int $templateId = 0): void
    {
        $url = add_query_arg(
            [
                'bs_fabric_notice' => $status,
                'bs_fabric_message' => rawurlencode($message),
            ] + ($action !== 'list' ? ['action' => $action] : [])
            + ($templateId > 0 ? ['template_id' => $templateId] : []),
            SchemaScreen::url(SchemaScreen::TAB_TEMPLATES)
        );

        wp_safe_redirect($url);
        exit;
    }
}
