<?php

declare(strict_types=1);

namespace BsFabric\Admin\Screens;

use BsFabric\Fields\DuplicateFieldNameException;
use BsFabric\Fields\FieldInUseException;
use BsFabric\Fields\FieldNotFoundException;
use BsFabric\Fields\FieldRegistry;
use BsFabric\Fields\FieldRepository;
use InvalidArgumentException;

final class FieldsScreen
{
    public const SLUG = 'bs-fabric-fields';

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

        echo '<div class="wrap bs-fabric-wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Fields', 'bs-fabric') . '</h1>';
        echo '<a href="' . esc_url($this->pageUrl('add')) . '" class="page-title-action">';
        echo esc_html__('Neues Feld', 'bs-fabric') . '</a>';
        echo '<hr class="wp-header-end">';

        $this->renderAdminNotices();

        if ($fields === []) {
            echo '<p>' . esc_html__('Noch keine Felder vorhanden.', 'bs-fabric') . '</p>';
            echo '</div>';

            return;
        }

        echo '<table class="wp-list-table widefat fixed striped bs-fabric-fields-table">';
        echo '<thead><tr>';
        echo '<th scope="col">' . esc_html__('Name', 'bs-fabric') . '</th>';
        echo '<th scope="col">' . esc_html__('Typ', 'bs-fabric') . '</th>';
        echo '<th scope="col">' . esc_html__('Label', 'bs-fabric') . '</th>';
        echo '<th scope="col">' . esc_html__('Aktionen', 'bs-fabric') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($fields as $field) {
            $typeLabel = isset($types[$field['type']])
                ? $types[$field['type']]->getLabel()
                : $field['type'];

            echo '<tr>';
            echo '<td><code>' . esc_html($field['name']) . '</code></td>';
            echo '<td>' . esc_html($typeLabel) . '</td>';
            echo '<td>' . esc_html($field['label']) . '</td>';
            echo '<td class="bs-fabric-row-actions">';
            echo '<a href="' . esc_url($this->pageUrl('edit', $field['id'])) . '">';
            echo esc_html__('Bearbeiten', 'bs-fabric') . '</a> | ';
            echo '<button type="button" class="button-link bs-fabric-delete-field" data-field-id="' . esc_attr((string) $field['id']) . '" data-field-name="' . esc_attr($field['name']) . '">';
            echo esc_html__('Löschen', 'bs-fabric') . '</button>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private function renderAddForm(): void
    {
        $types = $this->fieldRegistry->all();

        echo '<div class="wrap bs-fabric-wrap">';
        echo '<h1>' . esc_html__('Neues Feld', 'bs-fabric') . '</h1>';
        $this->renderAdminNotices();

        echo '<form method="post" action="' . esc_url($this->pageUrl('add')) . '" class="bs-fabric-form">';
        wp_nonce_field(self::NONCE_ACTION . '_create');
        echo '<input type="hidden" name="bs_fabric_action" value="create" />';

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="bs-fabric-name">' . esc_html__('Name', 'bs-fabric') . '</label></th>';
        echo '<td><input name="name" id="bs-fabric-name" type="text" class="regular-text" required />';
        echo '<p class="description">' . esc_html__('Eindeutiger slug-basierter Identifier. Nach Anlage nicht änderbar.', 'bs-fabric') . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="bs-fabric-label">' . esc_html__('Label', 'bs-fabric') . '</label></th>';
        echo '<td><input name="label" id="bs-fabric-label" type="text" class="regular-text" required /></td></tr>';

        echo '<tr><th scope="row"><label for="bs-fabric-type">' . esc_html__('Typ', 'bs-fabric') . '</label></th><td>';
        echo '<select name="type" id="bs-fabric-type" required>';

        foreach ($types as $type) {
            echo '<option value="' . esc_attr($type->getType()) . '">' . esc_html($type->getLabel()) . '</option>';
        }

        echo '</select></td></tr>';
        echo '</tbody></table>';

        submit_button(__('Feld anlegen', 'bs-fabric'));
        echo ' <a class="button" href="' . esc_url($this->pageUrl()) . '">' . esc_html__('Abbrechen', 'bs-fabric') . '</a>';
        echo '</form></div>';
    }

    private function renderEditForm(): void
    {
        $fieldId = isset($_GET['field_id']) ? (int) wp_unslash($_GET['field_id']) : 0;
        $field = $this->fieldRepository->getById($fieldId);

        if ($field === null) {
            echo '<div class="wrap bs-fabric-wrap">';
            echo '<h1>' . esc_html__('Feld bearbeiten', 'bs-fabric') . '</h1>';
            echo '<div class="notice notice-error"><p>' . esc_html__('Feld nicht gefunden.', 'bs-fabric') . '</p></div>';
            echo '</div>';

            return;
        }

        $fieldType = $this->fieldRegistry->get($field['type']);

        echo '<div class="wrap bs-fabric-wrap">';
        echo '<h1>' . esc_html__('Feld bearbeiten', 'bs-fabric') . '</h1>';
        $this->renderAdminNotices();

        echo '<form method="post" action="' . esc_url($this->pageUrl('edit', $fieldId)) . '" class="bs-fabric-form">';
        wp_nonce_field(self::NONCE_ACTION . '_update');
        echo '<input type="hidden" name="bs_fabric_action" value="update" />';
        echo '<input type="hidden" name="field_id" value="' . esc_attr((string) $fieldId) . '" />';

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row">' . esc_html__('Name', 'bs-fabric') . '</th>';
        echo '<td><code>' . esc_html($field['name']) . '</code></td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Typ', 'bs-fabric') . '</th>';
        echo '<td>' . esc_html($fieldType->getLabel()) . '</td></tr>';

        echo '<tr><th scope="row"><label for="bs-fabric-label">' . esc_html__('Label', 'bs-fabric') . '</label></th>';
        echo '<td><input name="label" id="bs-fabric-label" type="text" class="regular-text" value="' . esc_attr($field['label']) . '" required /></td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Einstellungen', 'bs-fabric') . '</th>';
        echo '<td>' . wp_kses_post($fieldType->renderSettings($field)) . '</td></tr>';

        echo '</tbody></table>';

        submit_button(__('Speichern', 'bs-fabric'));
        echo ' <a class="button" href="' . esc_url($this->pageUrl()) . '">' . esc_html__('Zurück zur Liste', 'bs-fabric') . '</a>';
        echo '</form></div>';
    }

    /**
     * @return array<string, mixed>
     */
    private function collectSettingsFromPost(string $type): array
    {
        if (! isset($_POST['bs_fabric_field_settings']) || ! is_array($_POST['bs_fabric_field_settings'])) {
            return $this->fieldRegistry->get($type)->getDefaultSettings();
        }

        $rawSettings = wp_unslash($_POST['bs_fabric_field_settings']);
        $field = $this->fieldRepository->getById(
            isset($_POST['field_id']) ? (int) wp_unslash($_POST['field_id']) : 0
        );
        $fieldName = is_array($field) ? (string) $field['name'] : sanitize_key($type);

        if (! isset($rawSettings[$fieldName]) || ! is_array($rawSettings[$fieldName])) {
            return $this->fieldRegistry->get($type)->getDefaultSettings();
        }

        return array_merge(
            $this->fieldRegistry->get($type)->getDefaultSettings(),
            $rawSettings[$fieldName]
        );
    }

    private function isFieldsPageRequest(): bool
    {
        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

        return $page === self::SLUG;
    }

    private function verifyNonce(string $action): bool
    {
        $nonce = isset($_POST['_wpnonce']) ? (string) wp_unslash($_POST['_wpnonce']) : '';

        return wp_verify_nonce($nonce, $action) !== false;
    }

    private function pageUrl(string $action = 'list', int $fieldId = 0): string
    {
        $args = ['page' => self::SLUG];

        if ($action !== 'list') {
            $args['action'] = $action;
        }

        if ($fieldId > 0) {
            $args['field_id'] = $fieldId;
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    private function redirectWithNotice(string $status, string $message, string $action = 'list', int $fieldId = 0): void
    {
        $url = add_query_arg(
            [
                'page' => self::SLUG,
                'bs_fabric_notice' => $status,
                'bs_fabric_message' => rawurlencode($message),
            ] + ($action !== 'list' ? ['action' => $action] : [])
            + ($fieldId > 0 ? ['field_id' => $fieldId] : []),
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
