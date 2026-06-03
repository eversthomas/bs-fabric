<?php

declare(strict_types=1);

namespace BsFabric\Admin;

use BsFabric\Fields\FieldRepository;
use BsFabric\Templates\TemplateRepository;

final class SchemaExporter
{
    public function __construct(
        private readonly FieldRepository $fieldRepository,
        private readonly TemplateRepository $templateRepository,
    ) {
    }

    public function toJson(): string
    {
        $payload = [
            'version' => BS_FABRIC_VERSION,
            'exported_at' => gmdate('c'),
            'fields' => $this->exportFields(),
            'templates' => $this->exportTemplates(),
        ];

        $encoded = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '{}';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportFields(): array
    {
        $fields = [];

        foreach ($this->fieldRepository->getAll() as $field) {
            $fields[] = [
                'name' => (string) $field['name'],
                'label' => (string) $field['label'],
                'type' => (string) $field['type'],
                'settings' => is_array($field['settings'] ?? null) ? $field['settings'] : [],
            ];
        }

        return $fields;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportTemplates(): array
    {
        $templates = [];

        foreach ($this->templateRepository->getAll() as $template) {
            $assignedFields = [];

            foreach ($this->templateRepository->getFields($template->getId()) as $assignment) {
                $assignedFields[] = [
                    'name' => (string) $assignment['name'],
                    'sort_order' => (int) ($assignment['sort_order'] ?? 0),
                    'is_required' => ! empty($assignment['is_required']),
                ];
            }

            $templates[] = [
                'name' => $template->name,
                'label' => $template->label,
                'title_field' => $template->title_field,
                'settings' => $template->settings,
                'fields' => $assignedFields,
            ];
        }

        return $templates;
    }
}
