<?php

declare(strict_types=1);

namespace BsFabric\Admin\Screens;

use BsFabric\Admin\MenuManager;
use BsFabric\Entries\EntryNotFoundException;
use BsFabric\Entries\EntryRepository;
use BsFabric\Fields\FieldRegistry;
use BsFabric\Fields\FieldRepository;
use BsFabric\Relations\RelationRepository;
use BsFabric\Templates\TemplateNotFoundException;
use BsFabric\Templates\TemplateRepository;
use InvalidArgumentException;

final class EntryEditScreen
{
    public const SLUG = 'bs-fabric-entry-edit';

    public const NONCE_ACTION = 'bs_fabric_entry_edit';

    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly TemplateRepository $templateRepository,
        private readonly FieldRepository $fieldRepository,
        private readonly FieldRegistry $fieldRegistry,
        private readonly RelationRepository $relationRepository,
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
            $this->renderEditForm($templateId, $entryId, $template->label);

            return;
        }

        if ($isAdd) {
            $this->renderEditForm($templateId, 0, $template->label);

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
                'fields' => $this->collectFieldsFromPost($templateId),
            ]);
        } catch (TemplateNotFoundException) {
            wp_die(esc_html__('Template nicht gefunden.', 'bs-fabric'));
        } catch (InvalidArgumentException $exception) {
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
                'fields' => $this->collectFieldsFromPost($templateId),
            ]);
        } catch (EntryNotFoundException) {
            wp_die(esc_html__('Entry nicht gefunden.', 'bs-fabric'));
        } catch (InvalidArgumentException $exception) {
            $this->redirectWithNotice('error', $exception->getMessage(), $templateId, $entryId);
        }

        $this->redirectWithNotice(
            'success',
            __('Entry wurde gespeichert.', 'bs-fabric'),
            $templateId,
            $entryId
        );
    }

    private function renderEditForm(int $templateId, int $entryId, string $templateLabel): void
    {
        $isNew = $entryId <= 0;
        $entry = $isNew ? null : $this->entryRepository->getById($entryId);
        $templateFields = $this->templateRepository->getFields($templateId);
        $fieldValues = $this->loadFieldValues($templateId, $entryId, $templateFields);
        $status = $isNew ? 'draft' : (string) $entry?->status;

        echo '<div class="wrap bs-fabric-wrap bs-fabric-entry-edit"';
        echo ' data-template-id="' . esc_attr((string) $templateId) . '"';
        echo ' data-entry-id="' . esc_attr((string) $entryId) . '">';

        echo '<h1>' . esc_html($isNew ? __('Neuer Entry', 'bs-fabric') : __('Entry bearbeiten', 'bs-fabric'));
        echo ' <span class="subtitle">' . esc_html($templateLabel) . '</span></h1>';

        $this->renderAdminNotices();

        echo '<form method="post" action="' . esc_url($this->formActionUrl($templateId, $entryId)) . '" class="bs-fabric-form bs-fabric-entry-form">';
        wp_nonce_field(self::NONCE_ACTION . ($isNew ? '_create' : '_update'));
        echo '<input type="hidden" name="bs_fabric_action" value="' . esc_attr($isNew ? 'create' : 'update') . '" />';
        echo '<input type="hidden" name="template_id" value="' . esc_attr((string) $templateId) . '" />';

        if (! $isNew) {
            echo '<input type="hidden" name="entry_id" value="' . esc_attr((string) $entryId) . '" />';
        }

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="bs-fabric-entry-status">' . esc_html__('Status', 'bs-fabric') . '</label></th><td>';
        echo '<select name="status" id="bs-fabric-entry-status">';

        foreach (['draft' => __('Entwurf', 'bs-fabric'), 'published' => __('Veröffentlicht', 'bs-fabric'), 'archived' => __('Archiviert', 'bs-fabric')] as $value => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected($status, $value, false),
                esc_html($label)
            );
        }

        echo '</select></td></tr>';

        foreach ($templateFields as $assignment) {
            $field = $this->fieldRepository->getById($assignment['field_id']);

            if ($field === null) {
                continue;
            }

            $value = $fieldValues[$field['name']] ?? null;
            $required = (bool) $assignment['is_required'];
            $requiredMark = $required ? ' <span class="bs-fabric-required">*</span>' : '';

            echo '<tr class="bs-fabric-field-row bs-fabric-field-row-' . esc_attr($field['type']) . '">';
            echo '<th scope="row"><label>' . esc_html($field['label']) . $requiredMark . '</label></th>';
            echo '<td>';

            if ($field['type'] === 'relation') {
                echo $this->renderRelationInput($field, $value, $entryId, $required);
            } else {
                $input = $this->fieldRegistry->get($field['type'])->renderInput($field, $value);

                if ($required) {
                    $input = $this->markInputRequired($input);
                }

                $this->echoFieldInput($input);
            }

            echo '</td></tr>';
        }

        echo '</tbody></table>';

        submit_button($isNew ? __('Entry anlegen', 'bs-fabric') : __('Speichern', 'bs-fabric'));
        echo ' <a class="button" href="' . esc_url($this->listUrl($templateId)) . '">';
        echo esc_html__('Zurück zur Liste', 'bs-fabric') . '</a>';
        echo '</form></div>';
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
            if (! $assignment['is_required']) {
                continue;
            }

            $field = $this->fieldRepository->getById($assignment['field_id']);

            if ($field === null) {
                continue;
            }

            $value = $fields[$field['name']] ?? null;

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
        if ($type === 'relation') {
            if (! is_array($value)) {
                return $value === null || $value === '' || $value === 0;
            }

            return $value === [];
        }

        if ($type === 'image' || $type === 'file') {
            return $value === null || $value === '' || $value === 0 || $value === '0';
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

    /**
     * Field HTML is generated by internal FieldType classes (already escaped there).
     * wp_kses_post() must not be used — it strips input, textarea, button, and editor markup.
     */
    private function echoFieldInput(string $input): void
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $input;
    }

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

    private function renderAdminNotices(): void
    {
        if (! isset($_GET['bs_fabric_notice'], $_GET['bs_fabric_message'])) {
            return;
        }

        $status = sanitize_key((string) wp_unslash($_GET['bs_fabric_notice']));
        $message = sanitize_text_field(rawurldecode((string) wp_unslash($_GET['bs_fabric_message'])));
        $class = $status === 'success' ? 'notice-success' : 'notice-error';

        printf(
            '<div class="notice %s is-dismissible"><p>%s</p></div>',
            esc_attr($class),
            esc_html($message)
        );
    }
}
