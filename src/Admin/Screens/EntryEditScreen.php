<?php

declare(strict_types=1);

namespace BsFabric\Admin\Screens;

use BsFabric\Admin\Components\AdminShell;
use BsFabric\Admin\Components\Button;
use BsFabric\Admin\Components\FormField;
use BsFabric\Admin\Components\PageHead;
use BsFabric\Admin\Components\SchemaNotices;
use BsFabric\Admin\MenuManager;
use BsFabric\Core\PluginSettings;
use BsFabric\Core\TemplateAuthorization;
use BsFabric\Entries\EntryAuditRepository;
use BsFabric\Entries\EntryNotFoundException;
use BsFabric\Entries\EntryRepository;
use BsFabric\Templates\TemplateSettings;
use BsFabric\Fields\FieldRegistry;
use BsFabric\Fields\FieldRepository;
use BsFabric\Fields\Types\AddressField;
use BsFabric\Relations\RelationRepository;
use BsFabric\Templates\TemplateNotFoundException;
use BsFabric\Templates\Template;
use BsFabric\Templates\TemplateRepository;
use InvalidArgumentException;
use RuntimeException;

final class EntryEditScreen
{
    public const SLUG = 'bs-fabric-entry-edit';

    public const NONCE_ACTION = 'bs_fabric_entry_edit';

    public static function url(int $templateId, int $entryId = 0): string
    {
        $args = [
            'page' => self::SLUG,
            'template_id' => $templateId,
        ];

        if ($entryId > 0) {
            $args['entry_id'] = $entryId;
        } else {
            $args['action'] = 'add';
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly TemplateRepository $templateRepository,
        private readonly FieldRepository $fieldRepository,
        private readonly FieldRegistry $fieldRegistry,
        private readonly RelationRepository $relationRepository,
        private readonly EntryAuditRepository $entryAuditRepository,
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handleActions']);
    }

    public function handleActions(): void
    {
        if (! $this->isEntryEditRequest()) {
            return;
        }

        if (! current_user_can('bs_fabric_manage_entries')) {
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
        if (! current_user_can('bs_fabric_manage_entries')) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'bs-fabric'));
        }

        $templateId = isset($_GET['template_id']) ? (int) wp_unslash($_GET['template_id']) : 0;
        $entryId = isset($_GET['entry_id']) ? (int) wp_unslash($_GET['entry_id']) : 0;
        $isAdd = isset($_GET['action']) && sanitize_key((string) wp_unslash($_GET['action'])) === 'add';

        $template = $this->templateRepository->getById($templateId);

        if ($template === null) {
            wp_die(esc_html__('Template nicht gefunden.', 'bs-fabric'));
        }

        if ($entryId > 0) {
            $this->renderEditForm($template, $entryId);

            return;
        }

        if ($isAdd) {
            $this->renderEditForm($template, 0);

            return;
        }

        wp_die(esc_html__('Ungültige Entry-Anfrage.', 'bs-fabric'));
    }

    private function handleCreate(): void
    {
        if (! $this->verifyNonce(self::NONCE_ACTION . '_create')) {
            wp_die(esc_html__('Sicherheitsüberprüfung fehlgeschlagen.', 'bs-fabric'));
        }

        $templateId = isset($_POST['template_id']) ? (int) wp_unslash($_POST['template_id']) : 0;
        $status = isset($_POST['status']) ? (string) wp_unslash($_POST['status']) : 'draft';

        $errors = $this->validateRequiredFields($templateId);

        if ($errors !== []) {
            $this->redirectWithNotice('error', implode(' ', $errors), $templateId, 0, true);
        }

        try {
            $entryId = $this->entryRepository->create([
                'template_id' => $templateId,
                'status' => $status,
                'slug' => $this->collectSlugFromPost(),
                'fields' => $this->collectFieldsFromPost($templateId),
            ]);
        } catch (TemplateNotFoundException) {
            wp_die(esc_html__('Template nicht gefunden.', 'bs-fabric'));
        } catch (InvalidArgumentException $exception) {
            $this->redirectWithNotice('error', $exception->getMessage(), $templateId, 0, true);
        } catch (RuntimeException $exception) {
            $this->redirectWithNotice('error', $exception->getMessage(), $templateId, 0, true);
        }

        $this->redirectWithNotice(
            'success',
            __('Entry wurde angelegt.', 'bs-fabric'),
            $templateId,
            $entryId
        );
    }

    private function handleUpdate(): void
    {
        if (! $this->verifyNonce(self::NONCE_ACTION . '_update')) {
            wp_die(esc_html__('Sicherheitsüberprüfung fehlgeschlagen.', 'bs-fabric'));
        }

        $templateId = isset($_POST['template_id']) ? (int) wp_unslash($_POST['template_id']) : 0;
        $entryId = isset($_POST['entry_id']) ? (int) wp_unslash($_POST['entry_id']) : 0;
        $status = isset($_POST['status']) ? (string) wp_unslash($_POST['status']) : 'draft';

        $errors = $this->validateRequiredFields($templateId);

        if ($errors !== []) {
            $this->redirectWithNotice('error', implode(' ', $errors), $templateId, $entryId);
        }

        try {
            $this->entryRepository->update($entryId, [
                'status' => $status,
                'slug' => $this->collectSlugFromPost(),
                'fields' => $this->collectFieldsFromPost($templateId),
            ]);
        } catch (EntryNotFoundException) {
            wp_die(esc_html__('Entry nicht gefunden.', 'bs-fabric'));
        } catch (InvalidArgumentException $exception) {
            $this->redirectWithNotice('error', $exception->getMessage(), $templateId, $entryId);
        } catch (RuntimeException $exception) {
            $this->redirectWithNotice('error', $exception->getMessage(), $templateId, $entryId);
        }

        $this->entryRepository->updateEditorNote(
            $entryId,
            isset($_POST['editor_note']) ? sanitize_textarea_field((string) wp_unslash($_POST['editor_note'])) : ''
        );

        $this->redirectWithNotice(
            'success',
            __('Entry wurde gespeichert.', 'bs-fabric'),
            $templateId,
            $entryId
        );
    }

    private function renderEditForm(Template $template, int $entryId): void
    {
        SchemaNotices::enqueueToastScript();

        $templateId = $template->getId();
        $isNew = $entryId <= 0;
        $entry = $isNew ? null : $this->entryRepository->getById($entryId);
        $templateFields = $this->templateRepository->getFields($templateId);
        $fieldValues = $this->loadFieldValues($templateId, $entryId, $templateFields);
        $status = $isNew ? PluginSettings::defaultEntryStatus() : (string) $entry?->status;

        AdminShell::open([
            ['label' => $template->label, 'url' => $this->listUrl($templateId)],
            ['label' => $isNew ? __('Neuer Eintrag', 'bs-fabric') : __('Bearbeiten', 'bs-fabric')],
        ]);

        PageHead::render(
            $isNew ? __('Neuer Eintrag', 'bs-fabric') : __('Eintrag bearbeiten', 'bs-fabric'),
            $template->label,
            Button::render($isNew ? __('Eintrag anlegen', 'bs-fabric') : __('Speichern', 'bs-fabric'), [
                'variant' => 'primary',
                'icon' => 'save',
                'type' => 'submit',
                'form' => 'bsf-entry-form',
            ]) . Button::render(__('Zurück', 'bs-fabric'), [
                'variant' => 'ghost',
                'href' => $this->listUrl($templateId),
            ])
        );

        echo '<form method="post" action="' . esc_url($this->formActionUrl($templateId, $entryId)) . '"';
        echo ' id="bsf-entry-form" class="bs-fabric-form bs-fabric-entry-form">';
        wp_nonce_field(self::NONCE_ACTION . ($isNew ? '_create' : '_update'));
        echo '<input type="hidden" name="bs_fabric_action" value="' . esc_attr($isNew ? 'create' : 'update') . '" />';
        echo '<input type="hidden" name="template_id" value="' . esc_attr((string) $templateId) . '" />';

        if (! $isNew) {
            echo '<input type="hidden" name="entry_id" value="' . esc_attr((string) $entryId) . '" />';
        }

        echo '<div class="bsf-entry-editor bs-fabric-entry-edit"';
        echo ' data-template-id="' . esc_attr((string) $templateId) . '"';
        echo ' data-entry-id="' . esc_attr((string) $entryId) . '">';

        echo '<div class="bsf-card"><div class="bsf-card-body"><div class="bsf-entry-fields">';

        foreach ($templateFields as $assignment) {
            $field = $this->fieldRepository->getById($assignment['field_id']);

            if ($field === null) {
                continue;
            }

            $value = $fieldValues[$field['name']] ?? null;
            $required = (bool) $assignment['is_required'];
            $fullWidth = $this->isFullWidthField($field['type']);
            $fieldClass = 'bsf-entry-field' . ($fullWidth ? ' bsf-entry-field-full' : '');

            $inputHtml = '';

            if ($field['type'] === 'relation') {
                $inputHtml = $this->renderRelationInput($field, $value, $entryId, $required);
            } else {
                $fieldForInput = $field;

                if ($required) {
                    $fieldForInput['_template_required'] = true;
                }

                $input = $this->fieldRegistry->get($field['type'])->renderInput($fieldForInput, $value);

                if ($required && $field['type'] !== 'address') {
                    $input = $this->markInputRequired($input);
                }

                $inputHtml = $input;
            }

            echo '<div class="' . esc_attr($fieldClass) . ' bs-fabric-field-row bs-fabric-field-row-' . esc_attr($field['type']) . '">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo FormField::render(
                $field['label'],
                $inputHtml,
                '',
                $required
            );
            echo '</div>';
        }

        echo '</div></div></div>';

        echo '<aside class="bsf-entry-sidebar">';
        echo '<div class="bsf-card"><div class="bsf-card-head"><h3>' . esc_html__('Veröffentlichung', 'bs-fabric') . '</h3></div>';
        echo '<div class="bsf-card-body">';
        echo '<div class="bsf-status-options" role="radiogroup" aria-label="' . esc_attr__('Status', 'bs-fabric') . '">';

        foreach ([
            'draft' => __('Entwurf', 'bs-fabric'),
            'published' => __('Veröffentlicht', 'bs-fabric'),
            'archived' => __('Archiviert', 'bs-fabric'),
        ] as $value => $label) {
            printf(
                '<label class="bsf-status-option"><input type="radio" name="status" value="%s"%s /><span class="bsf-status-option-label">%s</span></label>',
                esc_attr($value),
                checked($status, $value, false),
                esc_html($label)
            );
        }

        echo '</div>';

        $this->renderSlugField($template, $entry, $isNew);

        if (! $isNew && $entry !== null) {
            echo '<hr class="bsf-divider" />';
            echo '<div class="bsf-meta-list">';
            $this->renderMetaRow(__('Eintrag-ID', 'bs-fabric'), '#' . $entryId);
            $this->renderMetaRow(__('Erstellt', 'bs-fabric'), $this->formatDateTime((string) $entry->created_at));
            $this->renderMetaRow(__('Geändert', 'bs-fabric'), $this->formatDateTime((string) $entry->updated_at));

            $authorId = $entry->created_by ?? 0;
            $authorName = is_int($authorId) && $authorId > 0
                ? (string) get_the_author_meta('display_name', $authorId)
                : __('Unbekannt', 'bs-fabric');
            $this->renderMetaRow(__('Angelegt von', 'bs-fabric'), $authorName);

            $updatedById = $entry->updated_by ?? 0;
            $updatedByName = is_int($updatedById) && $updatedById > 0
                ? (string) get_the_author_meta('display_name', $updatedById)
                : __('—', 'bs-fabric');
            $this->renderMetaRow(__('Zuletzt geändert von', 'bs-fabric'), $updatedByName);
            $this->renderMetaRow(__('Felder', 'bs-fabric'), (string) count($templateFields));
            echo '</div>';

            $this->renderEditorNoteField($entryId);
            $this->renderAuditLog($entryId);

            if (TemplateAuthorization::canManageSchema()) {
                echo '<hr class="bsf-divider" />';
                echo Button::render(__('In Einstellungen öffnen', 'bs-fabric'), [
                    'variant' => 'ghost',
                    'size' => 'sm',
                    'href' => SchemaScreen::url(SchemaScreen::TAB_TEMPLATES, [
                        'action' => 'edit',
                        'template_id' => $templateId,
                    ]),
                ]);
            }
        }

        echo '</div></div></aside></div></form>';

        AdminShell::close();
    }

    private function renderMetaRow(string $label, string $value): void
    {
        echo '<div class="bsf-meta-row">';
        echo '<span class="bsf-meta-key">' . esc_html($label) . '</span>';
        echo '<span class="bsf-meta-val">' . esc_html($value) . '</span>';
        echo '</div>';
    }

    private function renderEditorNoteField(int $entryId): void
    {
        $note = $this->entryRepository->getEditorNote($entryId);

        echo '<hr class="bsf-divider" />';
        echo '<div class="bsf-meta-list">';
        echo '<span class="bsf-flabel">' . esc_html__('Redaktionsnotiz', 'bs-fabric') . '</span>';
        echo '<p class="bsf-fhint" style="margin:4px 0 8px">' . esc_html__(
            'Nur im Admin sichtbar — z. B. wo dieser Eintrag auf der Website eingebunden ist.',
            'bs-fabric'
        ) . '</p>';
        echo '<textarea name="editor_note" class="bsf-textarea" rows="3" style="width:100%">'
            . esc_textarea($note)
            . '</textarea>';
        echo '</div>';
    }

    private function renderAuditLog(int $entryId): void
    {
        $events = $this->entryAuditRepository->getForEntry($entryId);

        if ($events === []) {
            return;
        }

        echo '<hr class="bsf-divider" />';
        echo '<div class="bsf-meta-list">';
        echo '<span class="bsf-flabel">' . esc_html__('Verlauf', 'bs-fabric') . '</span>';

        foreach ($events as $event) {
            $actionLabel = match ($event['action']) {
                EntryAuditRepository::ACTION_CREATED => __('angelegt', 'bs-fabric'),
                EntryAuditRepository::ACTION_UPDATED => __('geändert', 'bs-fabric'),
                EntryAuditRepository::ACTION_DELETED => __('gelöscht', 'bs-fabric'),
                default => $event['action'],
            };

            $line = sprintf(
                /* translators: 1: user name, 2: action label, 3: formatted datetime */
                __('%1$s — %2$s (%3$s)', 'bs-fabric'),
                $event['user_name'],
                $actionLabel,
                $this->formatDateTime($event['created_at'])
            );

            echo '<p class="bsf-muted" style="font-size:12px;margin:6px 0 0">' . esc_html($line) . '</p>';
        }

        echo '</div>';
    }

    private function formatDateTime(string $datetime): string
    {
        if ($datetime === '') {
            return '—';
        }

        $timestamp = strtotime($datetime);

        return $timestamp !== false
            ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)
            : $datetime;
    }

    private function isFullWidthField(string $type): bool
    {
        return in_array($type, ['textarea', 'richtext', 'code', 'relation', 'reference', 'image', 'file', 'address'], true);
    }

    /**
     * @param array<string, mixed> $field
     * @param mixed $value
     */
    private function renderRelationInput(array $field, mixed $value, int $entryId, bool $required): string
    {
        $settings = is_array($field['settings'] ?? null) ? $field['settings'] : [];
        $targetTemplate = sanitize_key((string) ($settings['template'] ?? ''));
        $multiple = (bool) ($settings['multiple'] ?? false);
        $relatedIds = is_array($value) ? array_map('intval', $value) : [];

        if ($relatedIds === [] && is_numeric($value)) {
            $relatedIds = [(int) $value];
        }

        $name = 'bs_fabric_fields[' . $field['name'] . ']' . ($multiple ? '[]' : '');
        $listId = 'bs-fabric-relation-list-' . sanitize_key($field['name']);

        $itemsHtml = '';

        foreach ($relatedIds as $relatedId) {
            if ($relatedId <= 0) {
                continue;
            }

            $related = $this->entryRepository->getById($relatedId);
            $label = $related !== null && $related->title !== null && $related->title !== ''
                ? (string) $related->title
                : sprintf('#%d', $relatedId);

            $itemsHtml .= sprintf(
                '<li class="bs-fabric-relation-item" data-entry-id="%d">'
                . '<span class="bs-fabric-relation-handle">⋮⋮</span>'
                . '<span class="bs-fabric-relation-label">%s</span>'
                . '<button type="button" class="button-link bs-fabric-relation-remove">%s</button>'
                . '<input type="hidden" name="%s" value="%d" />'
                . '</li>',
                $relatedId,
                esc_html($label),
                esc_html__('Entfernen', 'bs-fabric'),
                esc_attr($name),
                $relatedId
            );
        }

        $requiredAttr = $required ? ' data-required="1"' : '';

        return sprintf(
            '<div class="bs-fabric-relation-field"%s data-field-name="%s" data-target-template="%s" data-multiple="%s">'
            . '<div class="bs-fabric-relation-search-wrap">'
            . '<input type="search" class="bs-fabric-relation-search regular-text" placeholder="%s" autocomplete="off" />'
            . '<ul class="bs-fabric-relation-results"></ul>'
            . '</div>'
            . '<ul id="%s" class="bs-fabric-relation-selected">%s</ul>'
            . '<p class="description">%s</p>'
            . '</div>',
            $requiredAttr,
            esc_attr($field['name']),
            esc_attr($targetTemplate),
            $multiple ? '1' : '0',
            esc_attr__('Entry suchen…', 'bs-fabric'),
            esc_attr($listId),
            $itemsHtml,
            esc_html__('Ausgewählte Einträge per Drag & Drop sortieren.', 'bs-fabric')
        );
    }

    /**
     * @param list<array{
     *     assignment_id: int,
     *     field_id: int,
     *     sort_order: int,
     *     is_required: bool,
     *     name: string,
     *     label: string,
     *     type: string
     * }> $templateFields
     *
     * @return array<string, mixed>
     */
    private function loadFieldValues(int $templateId, int $entryId, array $templateFields): array
    {
        if ($entryId <= 0) {
            return [];
        }

        $entry = $this->entryRepository->getById($entryId);

        if ($entry === null) {
            return [];
        }

        $data = $entry->toArray();
        $values = [];

        foreach ($templateFields as $assignment) {
            $field = $this->fieldRepository->getById($assignment['field_id']);

            if ($field === null) {
                continue;
            }

            if ($field['type'] === 'relation') {
                $values[$field['name']] = $this->relationRepository->get($entryId, $field['id']);

                continue;
            }

            $values[$field['name']] = $data[$field['name']] ?? null;
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectFieldsFromPost(int $templateId): array
    {
        $fields = [];
        $raw = isset($_POST['bs_fabric_fields']) && is_array($_POST['bs_fabric_fields'])
            ? wp_unslash($_POST['bs_fabric_fields'])
            : [];

        foreach ($this->templateRepository->getFields($templateId) as $assignment) {
            $field = $this->fieldRepository->getById($assignment['field_id']);

            if ($field === null) {
                continue;
            }

            $name = $field['name'];

            if (! array_key_exists($name, $raw)) {
                // Mehrfach-Reference ohne Auswahl: leeres Array senden, sonst bleiben alte IDs.
                if ($field['type'] === 'reference') {
                    $settings = is_array($field['settings'] ?? null) ? $field['settings'] : [];

                    if (! empty($settings['multiple'])) {
                        $fields[$name] = [];
                    }
                }

                continue;
            }

            $fields[$name] = $raw[$name];
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function validateRequiredFields(int $templateId): array
    {
        $errors = [];
        $fields = $this->collectFieldsFromPost($templateId);

        foreach ($this->templateRepository->getFields($templateId) as $assignment) {
            $field = $this->fieldRepository->getById($assignment['field_id']);

            if ($field === null) {
                continue;
            }

            $value = $fields[$field['name']] ?? null;
            $templateRequired = (bool) $assignment['is_required'];

            if ($field['type'] === 'address') {
                $addressField = $this->fieldRegistry->get('address');

                if ($addressField instanceof AddressField) {
                    $errors = array_merge(
                        $errors,
                        $addressField->getValidationErrors($value, $field, $templateRequired)
                    );
                }

                continue;
            }

            if (! $templateRequired) {
                continue;
            }

            if ($this->isEmptyValue($value, $field['type'])) {
                $errors[] = sprintf(
                    /* translators: %s: field label */
                    __('Pflichtfeld „%s“ ist leer.', 'bs-fabric'),
                    $field['label']
                );
            }
        }

        return $errors;
    }

    private function isEmptyValue(mixed $value, string $type): bool
    {
        if ($type === 'relation' || $type === 'reference') {
            if (! is_array($value)) {
                return $value === null || $value === '' || $value === 0 || $value === '0';
            }

            return $value === [];
        }

        if ($type === 'image' || $type === 'file') {
            return $value === null || $value === '' || $value === 0 || $value === '0';
        }

        if ($type === 'boolean') {
            return ! filter_var($value, FILTER_VALIDATE_BOOLEAN)
                && ! in_array($value, [1, '1', true], true);
        }

        if ($type === 'number') {
            return $value === null || $value === '' || ! is_numeric(is_scalar($value) ? (string) $value : '');
        }

        if ($type === 'address') {
            if (! is_array($value)) {
                return true;
            }

            return ($value['street'] ?? '') === ''
                && ($value['zip'] ?? '') === ''
                && ($value['city'] ?? '') === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return $value === null || $value === '';
    }

    private function markInputRequired(string $input): string
    {
        if (str_contains($input, ' required')) {
            return $input;
        }

        if (preg_match('/<(input|textarea|select)\b/', $input) === 1) {
            return (string) preg_replace('/<(input|textarea|select)\b/', '<$1 required', $input, 1);
        }

        return $input;
    }

    private function isEntryEditRequest(): bool
    {
        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

        return $page === self::SLUG;
    }

    private function verifyNonce(string $action): bool
    {
        $nonce = isset($_POST['_wpnonce']) ? (string) wp_unslash($_POST['_wpnonce']) : '';

        return wp_verify_nonce($nonce, $action) !== false;
    }

    private function collectSlugFromPost(): string
    {
        if (! isset($_POST['slug'])) {
            return '';
        }

        return sanitize_title((string) wp_unslash($_POST['slug']));
    }

    private function renderSlugField(Template $template, ?\BsFabric\Entries\Entry $entry, bool $isNew): void
    {
        $slug = '';

        if (! $isNew && $entry !== null && is_string($entry->slug)) {
            $slug = $entry->slug;
        }

        echo '<hr class="bsf-divider" />';
        echo '<label class="bsf-flabel" for="bs-fabric-entry-slug">' . esc_html__('URL-Slug', 'bs-fabric') . '</label>';
        echo '<input type="text" class="bsf-input" name="slug" id="bs-fabric-entry-slug" value="'
            . esc_attr($slug) . '" autocomplete="off" />';
        echo '<p class="bsf-fhint">' . esc_html__(
            'Leer lassen, um den Slug aus dem Titel zu erzeugen. Eindeutig pro Template.',
            'bs-fabric'
        ) . '</p>';

        if ($isNew || $entry === null || ! TemplateSettings::publicDetail($template->settings)) {
            return;
        }

        if ($entry->status !== 'published' || $slug === '') {
            echo '<p class="bsf-fhint">' . esc_html__(
                'Die öffentliche URL erscheint, sobald der Eintrag veröffentlicht ist und einen Slug hat.',
                'bs-fabric'
            ) . '</p>';

            return;
        }

        $url = home_url('/' . sanitize_key((string) $template->name) . '/' . $slug . '/');
        echo '<p class="bsf-fhint"><a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">'
            . esc_html($url) . '</a></p>';
    }

    /**
     * Field HTML is generated by internal FieldType classes (already escaped there).
     */
    private function formActionUrl(int $templateId, int $entryId): string
    {
        $args = [
            'page' => self::SLUG,
            'template_id' => $templateId,
        ];

        if ($entryId > 0) {
            $args['entry_id'] = $entryId;
        } else {
            $args['action'] = 'add';
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    private function listUrl(int $templateId): string
    {
        $template = $this->templateRepository->getById($templateId);

        if ($template === null) {
            return admin_url('admin.php?page=' . MenuManager::MENU_SLUG);
        }

        return admin_url('admin.php?page=' . EntryListScreen::slugForTemplate($template->name));
    }

    private function redirectWithNotice(
        string $status,
        string $message,
        int $templateId,
        int $entryId,
        bool $isAdd = false,
    ): void {
        $args = [
            'page' => self::SLUG,
            'template_id' => $templateId,
            'bs_fabric_notice' => $status,
            'bs_fabric_message' => rawurlencode($message),
        ];

        if ($entryId > 0) {
            $args['entry_id'] = $entryId;
        } elseif ($isAdd) {
            $args['action'] = 'add';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }
}
