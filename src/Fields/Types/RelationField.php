<?php

declare(strict_types=1);

namespace BsFabric\Fields\Types;

final class RelationField implements FieldTypeInterface
{
    public function getType(): string
    {
        return 'relation';
    }

    public function getLabel(): string
    {
        return __('Relation', 'bs-fabric');
    }

    public function getDefaultSettings(): array
    {
        return [
            'template' => '',
            'multiple' => false,
        ];
    }

    public function sanitizeValue(mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        $ids = is_array($value) ? $value : [$value];

        $sanitized = [];

        foreach ($ids as $id) {
            if (is_array($id)) {
                $id = $id['id'] ?? $id[0] ?? 0;
            }

            $entryId = absint($id);

            if ($entryId > 0) {
                $sanitized[] = $entryId;
            }
        }

        return array_values(array_unique($sanitized));
    }

    public function renderInput(array $field, mixed $value): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->fieldName($field);
        $entryIds = $this->sanitizeValue($value);
        $multiple = (bool) $settings['multiple'];
        $nameAttr = $multiple ? $name . '[]' : $name;
        $hiddenInputs = '';

        if ($multiple) {
            foreach ($entryIds as $entryId) {
                $hiddenInputs .= sprintf(
                    '<input type="hidden" name="%s" value="%d" />',
                    esc_attr($nameAttr),
                    $entryId
                );
            }
        } else {
            $singleId = $entryIds[0] ?? 0;
            $hiddenInputs = sprintf(
                '<input type="hidden" name="%s" value="%d" />',
                esc_attr($name),
                $singleId
            );
        }

        return sprintf(
            '<div class="bs-fabric-field bs-fabric-field-relation" data-bs-fabric-relation-field data-template="%s" data-multiple="%s">'
            . '%s'
            . '<ul class="bs-fabric-field-relation-list"></ul>'
            . '<button type="button" class="button bs-fabric-field-relation-add">%s</button>'
            . '</div>',
            esc_attr((string) $settings['template']),
            $multiple ? '1' : '0',
            $hiddenInputs,
            esc_html__('Eintrag verknüpfen', 'bs-fabric')
        );
    }

    public function renderSettings(array $field): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->settingsName($field);

        return sprintf(
            '<p class="bs-fabric-field-setting">'
            . '<label for="%1$s-template">%2$s</label>'
            . '<input type="text" id="%1$s-template" name="%1$s[template]" value="%3$s" class="regular-text" />'
            . '</p>'
            . '<p class="bs-fabric-field-setting">'
            . '<label><input type="checkbox" name="%1$s[multiple]" value="1"%4$s /> %5$s</label>'
            . '</p>',
            esc_attr($name),
            esc_html__('Ziel-Template (Name)', 'bs-fabric'),
            esc_attr((string) $settings['template']),
            checked((bool) $settings['multiple'], true, false),
            esc_html__('Mehrfach-Verknüpfung', 'bs-fabric')
        );
    }

    public function renderValue(mixed $value): mixed
    {
        $entryIds = $this->sanitizeValue($value);

        if ($entryIds === []) {
            return [];
        }

        return $entryIds;
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    private function mergeSettings(array $field): array
    {
        $stored = is_array($field['settings'] ?? null) ? $field['settings'] : [];

        return array_merge($this->getDefaultSettings(), $stored);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function fieldName(array $field): string
    {
        return 'bs_fabric_fields[' . (string) ($field['name'] ?? '') . ']';
    }

    /**
     * @param array<string, mixed> $field
     */
    private function settingsName(array $field): string
    {
        return 'bs_fabric_field_settings[' . (string) ($field['name'] ?? '') . ']';
    }
}
