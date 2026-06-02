<?php

declare(strict_types=1);

namespace BsFabric\Admin\Screens;

use BsFabric\Fields\FieldRepository;
use BsFabric\Templates\DuplicateTemplateNameException;
use BsFabric\Templates\TemplateNotFoundException;
use BsFabric\Templates\TemplateRepository;
use InvalidArgumentException;

final class TemplatesScreen
{
    public const SLUG = 'bs-fabric-templates';

    public const NONCE_ACTION = 'bs_fabric_templates';

    public function __construct(
        private readonly TemplateRepository $templateRepository,
        private readonly FieldRepository $fieldRepository,
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
        if (! current_user_can('bs_fabric_manage_schema')) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'bs-fabric'));
        }

        $action = isset($_GET['action'])
            ? sanitize_key((string) wp_unslash($_GET['action']))
            : 'list';

        match ($action) {
            'add' => $this->renderAddForm(),
            'edit' => $this->renderEditForm(),
            default => $this->renderList(),
        };
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
            $this->templateRepository->update($templateId, [
                'label' => $label,
                'title_field' => $titleField !== '' ? $titleField : null,
            ]);
        } catch (TemplateNotFoundException) {
            $this->redirectWithNotice('error', __('Template nicht gefunden.', 'bs-fabric'));
        } catch (InvalidArgumentException $exception) {
            $this->redirectWithNotice('error', $exception->getMessage(), 'edit', $templateId);
        }

        $this->redirectWithNotice(
            'success',
            __('Template wurde gespeichert.', 'bs-fabric'),
            'edit',
            $templateId
        );
    }

    private function renderList(): void
    {
        $templates = $this->templateRepository->getAll();

        echo '<div class="wrap bs-fabric-wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Templates', 'bs-fabric') . '</h1>';
        echo '<a href="' . esc_url($this->pageUrl('add')) . '" class="page-title-action">';
        echo esc_html__('Neues Template', 'bs-fabric') . '</a>';
        echo '<hr class="wp-header-end">';

        $this->renderAdminNotices();

        if ($templates === []) {
            echo '<p>' . esc_html__('Noch keine Templates vorhanden.', 'bs-fabric') . '</p>';
            echo '</div>';

            return;
        }

        echo '<table class="wp-list-table widefat fixed striped bs-fabric-templates-table">';
        echo '<thead><tr>';
        echo '<th scope="col">' . esc_html__('Name', 'bs-fabric') . '</th>';
        echo '<th scope="col">' . esc_html__('Label', 'bs-fabric') . '</th>';
        echo '<th scope="col">' . esc_html__('Titel-Feld', 'bs-fabric') . '</th>';
        echo '<th scope="col">' . esc_html__('Felder', 'bs-fabric') . '</th>';
        echo '<th scope="col">' . esc_html__('Aktionen', 'bs-fabric') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($templates as $template) {
            $fieldCount = count($this->templateRepository->getFields($template->getId()));

            echo '<tr>';
            echo '<td><code>' . esc_html($template->name) . '</code></td>';
            echo '<td>' . esc_html($template->label) . '</td>';
            echo '<td>' . esc_html($template->title_field ?? '—') . '</td>';
            echo '<td>' . esc_html((string) $fieldCount) . '</td>';
            echo '<td class="bs-fabric-row-actions">';
            echo '<a href="' . esc_url($this->pageUrl('edit', $template->getId())) . '">';
            echo esc_html__('Bearbeiten', 'bs-fabric') . '</a> | ';
            echo '<button type="button" class="button-link bs-fabric-delete-template" data-template-id="' . esc_attr((string) $template->getId()) . '">';
            echo esc_html__('Löschen', 'bs-fabric') . '</button>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private function renderAddForm(): void
    {
        echo '<div class="wrap bs-fabric-wrap">';
        echo '<h1>' . esc_html__('Neues Template', 'bs-fabric') . '</h1>';
        $this->renderAdminNotices();

        echo '<form method="post" action="' . esc_url($this->pageUrl('add')) . '" class="bs-fabric-form">';
        wp_nonce_field(self::NONCE_ACTION . '_create');
        echo '<input type="hidden" name="bs_fabric_action" value="create" />';

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="bs-fabric-template-name">' . esc_html__('Name', 'bs-fabric') . '</label></th>';
        echo '<td><input name="name" id="bs-fabric-template-name" type="text" class="regular-text" required />';
        echo '<p class="description">' . esc_html__('Eindeutiger slug-basierter Identifier. Nach Anlage nicht änderbar.', 'bs-fabric') . '</p></td></tr>';
        echo '<tr><th scope="row"><label for="bs-fabric-template-label">' . esc_html__('Label', 'bs-fabric') . '</label></th>';
        echo '<td><input name="label" id="bs-fabric-template-label" type="text" class="regular-text" required /></td></tr>';
        echo '</tbody></table>';

        submit_button(__('Template anlegen', 'bs-fabric'));
        echo ' <a class="button" href="' . esc_url($this->pageUrl()) . '">' . esc_html__('Abbrechen', 'bs-fabric') . '</a>';
        echo '</form></div>';
    }

    private function renderEditForm(): void
    {
        $templateId = isset($_GET['template_id']) ? (int) wp_unslash($_GET['template_id']) : 0;
        $template = $this->templateRepository->getById($templateId);

        if ($template === null) {
            echo '<div class="wrap bs-fabric-wrap">';
            echo '<h1>' . esc_html__('Template bearbeiten', 'bs-fabric') . '</h1>';
            echo '<div class="notice notice-error"><p>' . esc_html__('Template nicht gefunden.', 'bs-fabric') . '</p></div>';
            echo '</div>';

            return;
        }

        $assignedFields = $this->templateRepository->getFields($templateId);
        $allFields = $this->fieldRepository->getAll();
        $assignedFieldIds = array_column($assignedFields, 'field_id');
        $availableFields = array_values(array_filter(
            $allFields,
            static fn (array $field): bool => ! in_array($field['id'], $assignedFieldIds, true)
        ));

        echo '<div class="wrap bs-fabric-wrap bs-fabric-template-edit" data-template-id="' . esc_attr((string) $templateId) . '">';
        echo '<h1>' . esc_html__('Template bearbeiten', 'bs-fabric') . '</h1>';
        $this->renderAdminNotices();

        echo '<form method="post" action="' . esc_url($this->pageUrl('edit', $templateId)) . '" class="bs-fabric-form">';
        wp_nonce_field(self::NONCE_ACTION . '_update');
        echo '<input type="hidden" name="bs_fabric_action" value="update" />';
        echo '<input type="hidden" name="template_id" value="' . esc_attr((string) $templateId) . '" />';

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">' . esc_html__('Name', 'bs-fabric') . '</th>';
        echo '<td><code>' . esc_html($template->name) . '</code></td></tr>';
        echo '<tr><th scope="row"><label for="bs-fabric-template-label">' . esc_html__('Label', 'bs-fabric') . '</label></th>';
        echo '<td><input name="label" id="bs-fabric-template-label" type="text" class="regular-text" value="' . esc_attr($template->label) . '" required /></td></tr>';
        echo '<tr><th scope="row"><label for="bs-fabric-title-field">' . esc_html__('Titel-Feld', 'bs-fabric') . '</label></th><td>';
        echo '<select name="title_field" id="bs-fabric-title-field">';
        echo '<option value="">' . esc_html__('— Keins —', 'bs-fabric') . '</option>';

        foreach ($assignedFields as $field) {
            echo '<option value="' . esc_attr($field['name']) . '"';
            selected($template->title_field, $field['name'], false);
            echo '>' . esc_html($field['label'] . ' (' . $field['name'] . ')') . '</option>';
        }

        echo '</select>';
        echo '<p class="description">' . esc_html__('Welches zugewiesene Feld als Entry-Titel dient.', 'bs-fabric') . '</p>';
        echo '</td></tr>';
        echo '</tbody></table>';

        submit_button(__('Speichern', 'bs-fabric'));
        echo ' <a class="button" href="' . esc_url($this->pageUrl()) . '">' . esc_html__('Zurück zur Liste', 'bs-fabric') . '</a>';
        echo '</form>';

        echo '<hr />';
        echo '<h2>' . esc_html__('Felder zuweisen', 'bs-fabric') . '</h2>';

        if ($availableFields !== []) {
            echo '<div class="bs-fabric-template-add-field">';
            echo '<label for="bs-fabric-available-field" class="screen-reader-text">' . esc_html__('Feld hinzufügen', 'bs-fabric') . '</label>';
            echo '<select id="bs-fabric-available-field" class="bs-fabric-available-field">';
            echo '<option value="">' . esc_html__('Feld wählen…', 'bs-fabric') . '</option>';

            foreach ($availableFields as $field) {
                echo '<option value="' . esc_attr((string) $field['id']) . '">';
                echo esc_html($field['label'] . ' (' . $field['name'] . ' — ' . $field['type'] . ')');
                echo '</option>';
            }

            echo '</select> ';
            echo '<button type="button" class="button bs-fabric-assign-field">' . esc_html__('Feld hinzufügen', 'bs-fabric') . '</button>';
            echo '</div>';
        } else {
            echo '<p class="description">' . esc_html__('Alle verfügbaren Felder sind bereits zugewiesen.', 'bs-fabric') . '</p>';
        }

        echo '<ul id="bs-fabric-template-fields" class="bs-fabric-template-fields">';

        foreach ($assignedFields as $field) {
            $this->renderAssignedFieldItem($field);
        }

        echo '</ul>';
        echo '<p class="description">' . esc_html__('Reihenfolge per Drag & Drop ändern.', 'bs-fabric') . '</p>';
        echo '</div>';
    }

    /**
     * @param array{
     *     assignment_id: int,
     *     field_id: int,
     *     sort_order: int,
     *     is_required: bool,
     *     name: string,
     *     label: string,
     *     type: string
     * } $field
     */
    private function renderAssignedFieldItem(array $field): void
    {
        echo '<li class="bs-fabric-template-field-item" data-field-id="' . esc_attr((string) $field['field_id']) . '">';
        echo '<span class="bs-fabric-template-field-handle" aria-hidden="true">⋮⋮</span>';
        echo '<span class="bs-fabric-template-field-label">';
        echo esc_html($field['label'] . ' (' . $field['name'] . ')');
        echo ' <code>' . esc_html($field['type']) . '</code></span>';
        echo '<label class="bs-fabric-template-field-required">';
        echo '<input type="checkbox" class="bs-fabric-field-required-toggle" data-field-id="' . esc_attr((string) $field['field_id']) . '"';
        checked($field['is_required'], true);
        echo ' /> ' . esc_html__('Pflichtfeld', 'bs-fabric');
        echo '</label>';
        echo '<button type="button" class="button-link bs-fabric-remove-template-field" data-field-id="' . esc_attr((string) $field['field_id']) . '">';
        echo esc_html__('Entfernen', 'bs-fabric') . '</button>';
        echo '</li>';
    }

    private function isTemplatesPageRequest(): bool
    {
        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

        return $page === self::SLUG;
    }

    private function verifyNonce(string $action): bool
    {
        $nonce = isset($_POST['_wpnonce']) ? (string) wp_unslash($_POST['_wpnonce']) : '';

        return wp_verify_nonce($nonce, $action) !== false;
    }

    private function pageUrl(string $action = 'list', int $templateId = 0): string
    {
        $args = ['page' => self::SLUG];

        if ($action !== 'list') {
            $args['action'] = $action;
        }

        if ($templateId > 0) {
            $args['template_id'] = $templateId;
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    private function redirectWithNotice(string $status, string $message, string $action = 'list', int $templateId = 0): void
    {
        $url = add_query_arg(
            [
                'page' => self::SLUG,
                'bs_fabric_notice' => $status,
                'bs_fabric_message' => rawurlencode($message),
            ] + ($action !== 'list' ? ['action' => $action] : [])
            + ($templateId > 0 ? ['template_id' => $templateId] : []),
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
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
