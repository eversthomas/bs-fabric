<?php

declare(strict_types=1);

namespace BsFabric\Admin\Ajax;

use BsFabric\Admin\Screens\SchemaScreen;
use BsFabric\Fields\FieldAdminOperations;
use BsFabric\Fields\FieldInUseException;
use BsFabric\Fields\FieldNotFoundException;

final class FieldAjax
{
    public const NONCE_ACTION = 'bs_fabric_field_ajax';

    public function __construct(
        private readonly FieldAdminOperations $fieldRepository,
    ) {
    }

    public function register(): void
    {
        add_action('wp_ajax_bs_fabric_delete_field', [$this, 'deleteField']);
    }

    public function deleteField(): void
    {
        if (! current_user_can('bs_fabric_manage_schema')) {
            wp_send_json_error(
                ['message' => __('Keine Berechtigung.', 'bs-fabric')],
                403
            );
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $fieldId = isset($_POST['field_id']) ? (int) wp_unslash($_POST['field_id']) : 0;

        if ($fieldId <= 0) {
            wp_send_json_error(
                ['message' => __('Ungültige Feld-ID.', 'bs-fabric')],
                400
            );
        }

        try {
            $this->fieldRepository->delete($fieldId);
        } catch (FieldInUseException $exception) {
            $templates = $this->formatTemplatesForResponse($exception->getTemplates());

            wp_send_json_error(
                [
                    'message' => $this->buildFieldInUseMessage($exception->getTemplates()),
                    'templates' => $templates,
                ],
                409
            );
        } catch (FieldNotFoundException) {
            wp_send_json_error(
                ['message' => __('Feld nicht gefunden.', 'bs-fabric')],
                404
            );
        }

        wp_send_json_success(['message' => __('Feld wurde gelöscht.', 'bs-fabric')]);
    }

    /**
     * @param list<array{id: int, name: string, label: string}> $templates
     */
    private function buildFieldInUseMessage(array $templates): string
    {
        if ($templates === []) {
            return __('Feld wird in Templates verwendet und kann nicht gelöscht werden.', 'bs-fabric');
        }

        return __('Feld wird noch in Templates verwendet und kann nicht gelöscht werden. Betroffene Templates:', 'bs-fabric');
    }

    /**
     * @param list<array{id: int, name: string, label: string}> $templates
     *
     * @return list<array{id: int, name: string, label: string, url: string}>
     */
    private function formatTemplatesForResponse(array $templates): array
    {
        $formatted = [];

        foreach ($templates as $template) {
            $templateId = (int) ($template['id'] ?? 0);

            if ($templateId <= 0) {
                continue;
            }

            $formatted[] = [
                'id' => $templateId,
                'name' => (string) ($template['name'] ?? ''),
                'label' => (string) ($template['label'] ?? ''),
                'url' => SchemaScreen::url(
                    SchemaScreen::TAB_TEMPLATES,
                    [
                        'action' => 'edit',
                        'template_id' => $templateId,
                    ]
                ),
            ];
        }

        return $formatted;
    }
}
