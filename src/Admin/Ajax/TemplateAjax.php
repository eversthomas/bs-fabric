<?php

declare(strict_types=1);

namespace BsFabric\Admin\Ajax;

use BsFabric\Core\TemplateAuthorization;
use BsFabric\Fields\FieldNotFoundException;
use BsFabric\Templates\TemplateInUseException;
use BsFabric\Templates\TemplateNotFoundException;
use BsFabric\Templates\TemplateAdminOperations;

final class TemplateAjax
{
    public const NONCE_ACTION = 'bs_fabric_template_ajax';

    public function __construct(
        private readonly TemplateAdminOperations $templateRepository,
    ) {
    }

    public function register(): void
    {
        add_action('wp_ajax_bs_fabric_delete_template', [$this, 'deleteTemplate']);
        add_action('wp_ajax_bs_fabric_assign_template_field', [$this, 'assignField']);
        add_action('wp_ajax_bs_fabric_remove_template_field', [$this, 'removeField']);
        add_action('wp_ajax_bs_fabric_update_template_field_order', [$this, 'updateFieldOrder']);
        add_action('wp_ajax_bs_fabric_update_template_field_required', [$this, 'updateFieldRequired']);
        add_action('wp_ajax_bs_fabric_update_template_field_rest_hidden', [$this, 'updateFieldRestHidden']);
    }

    public function deleteTemplate(): void
    {
        $this->authorizeSchema();

        $templateId = $this->getTemplateId();

        try {
            $this->templateRepository->delete($templateId);
        } catch (TemplateInUseException) {
            wp_send_json_error(
                ['message' => __('Template hat Entries und kann nicht gelöscht werden.', 'bs-fabric')],
                409
            );
        } catch (TemplateNotFoundException) {
            wp_send_json_error(
                ['message' => __('Template nicht gefunden.', 'bs-fabric')],
                404
            );
        }

        wp_send_json_success(['message' => __('Template wurde gelöscht.', 'bs-fabric')]);
    }

    public function assignField(): void
    {
        $this->authorizeTemplateFields();

        $templateId = $this->getTemplateId();
        $fieldId = isset($_POST['field_id']) ? (int) wp_unslash($_POST['field_id']) : 0;
        $isRequired = isset($_POST['is_required']) && (string) wp_unslash($_POST['is_required']) === '1';

        if ($fieldId <= 0) {
            wp_send_json_error(['message' => __('Ungültige Feld-ID.', 'bs-fabric')], 400);
        }

        try {
            $this->templateRepository->assignField($templateId, $fieldId, $isRequired);
            $fields = $this->templateRepository->getFields($templateId);
        } catch (TemplateNotFoundException|FieldNotFoundException $exception) {
            wp_send_json_error(['message' => $exception->getMessage()], 404);
        }

        wp_send_json_success([
            'message' => __('Feld wurde zugewiesen.', 'bs-fabric'),
            'fields' => $fields,
        ]);
    }

    public function removeField(): void
    {
        $this->authorizeTemplateFields();

        $templateId = $this->getTemplateId();
        $fieldId = isset($_POST['field_id']) ? (int) wp_unslash($_POST['field_id']) : 0;

        if ($fieldId <= 0) {
            wp_send_json_error(['message' => __('Ungültige Feld-ID.', 'bs-fabric')], 400);
        }

        try {
            $removed = $this->templateRepository->removeField($templateId, $fieldId);
        } catch (TemplateNotFoundException $exception) {
            wp_send_json_error(['message' => $exception->getMessage()], 404);
        }

        if (! $removed) {
            wp_send_json_error(['message' => __('Feld konnte nicht entfernt werden.', 'bs-fabric')], 400);
        }

        wp_send_json_success(['message' => __('Feld wurde entfernt.', 'bs-fabric')]);
    }

    public function updateFieldOrder(): void
    {
        $this->authorizeTemplateFields();

        $templateId = $this->getTemplateId();
        $fieldIds = isset($_POST['field_ids']) ? wp_unslash($_POST['field_ids']) : [];

        if (! is_array($fieldIds)) {
            wp_send_json_error(['message' => __('Ungültige Feld-Reihenfolge.', 'bs-fabric')], 400);
        }

        $fieldIds = array_values(array_map(static fn (mixed $id): int => (int) $id, $fieldIds));

        try {
            $updated = $this->templateRepository->updateFieldOrder($templateId, $fieldIds);
        } catch (TemplateNotFoundException $exception) {
            wp_send_json_error(['message' => $exception->getMessage()], 404);
        }

        if (! $updated) {
            wp_send_json_error(['message' => __('Reihenfolge konnte nicht gespeichert werden.', 'bs-fabric')], 500);
        }

        wp_send_json_success(['message' => __('Reihenfolge gespeichert.', 'bs-fabric')]);
    }

    public function updateFieldRequired(): void
    {
        $this->authorizeTemplateFields();

        $templateId = $this->getTemplateId();
        $fieldId = isset($_POST['field_id']) ? (int) wp_unslash($_POST['field_id']) : 0;
        $isRequired = isset($_POST['is_required']) && (string) wp_unslash($_POST['is_required']) === '1';

        if ($fieldId <= 0) {
            wp_send_json_error(['message' => __('Ungültige Feld-ID.', 'bs-fabric')], 400);
        }

        try {
            $updated = $this->templateRepository->updateFieldRequired($templateId, $fieldId, $isRequired);
        } catch (TemplateNotFoundException $exception) {
            wp_send_json_error(['message' => $exception->getMessage()], 404);
        }

        if (! $updated) {
            wp_send_json_error(['message' => __('Pflichtfeld konnte nicht aktualisiert werden.', 'bs-fabric')], 500);
        }

        wp_send_json_success(['message' => __('Pflichtfeld aktualisiert.', 'bs-fabric')]);
    }

    public function updateFieldRestHidden(): void
    {
        $this->authorizeTemplateFields();

        $templateId = $this->getTemplateId();
        $fieldId = isset($_POST['field_id']) ? (int) wp_unslash($_POST['field_id']) : 0;
        $restHidden = isset($_POST['rest_hidden']) && (string) wp_unslash($_POST['rest_hidden']) === '1';

        if ($fieldId <= 0) {
            wp_send_json_error(['message' => __('Ungültige Feld-ID.', 'bs-fabric')], 400);
        }

        try {
            $updated = $this->templateRepository->updateFieldRestHidden($templateId, $fieldId, $restHidden);
        } catch (TemplateNotFoundException $exception) {
            wp_send_json_error(['message' => $exception->getMessage()], 404);
        }

        if (! $updated) {
            wp_send_json_error(['message' => __('REST-Sichtbarkeit konnte nicht aktualisiert werden.', 'bs-fabric')], 500);
        }

        wp_send_json_success(['message' => __('REST-Sichtbarkeit aktualisiert.', 'bs-fabric')]);
    }

    private function authorizeSchema(): void
    {
        if (! TemplateAuthorization::canManageSchema()) {
            wp_send_json_error(['message' => __('Keine Berechtigung.', 'bs-fabric')], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');
    }

    private function authorizeTemplateFields(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $templateId = $this->getTemplateId();
        $template = $this->templateRepository->getById($templateId);

        if (! TemplateAuthorization::canManageTemplateFields($template)) {
            wp_send_json_error(['message' => __('Keine Berechtigung.', 'bs-fabric')], 403);
        }
    }

    private function getTemplateId(): int
    {
        $templateId = isset($_POST['template_id']) ? (int) wp_unslash($_POST['template_id']) : 0;

        if ($templateId <= 0) {
            wp_send_json_error(['message' => __('Ungültige Template-ID.', 'bs-fabric')], 400);
        }

        return $templateId;
    }
}
