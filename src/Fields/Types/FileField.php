<?php

declare(strict_types=1);

namespace BsFabric\Fields\Types;

final class FileField implements FieldTypeInterface
{
    public function getType(): string
    {
        return 'file';
    }

    public function getLabel(): string
    {
        return __('File', 'bs-fabric');
    }

    public function getDefaultSettings(): array
    {
        return [
            'return_format' => 'id',
            'allowed_types' => [],
        ];
    }

    public function sanitizeValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_array($value)) {
            $value = $value['id'] ?? $value[0] ?? 0;
        }

        return absint($value);
    }

    public function renderInput(array $field, mixed $value): string
    {
        $name = $this->fieldName($field);
        $attachmentId = $this->sanitizeValue($value);
        $filename = $attachmentId > 0 ? basename((string) get_attached_file($attachmentId)) : '';

        return sprintf(
            '<div class="bs-fabric-field bs-fabric-field-file" data-bs-fabric-file-field>'
            . '<span class="bs-fabric-field-file-name">%s</span>'
            . '<input type="hidden" name="%s" value="%d" />'
            . '<button type="button" class="button bs-fabric-field-file-select">%s</button>'
            . '<button type="button" class="button bs-fabric-field-file-remove">%s</button>'
            . '</div>',
            esc_html($filename),
            esc_attr($name),
            $attachmentId,
            esc_html__('Datei wählen', 'bs-fabric'),
            esc_html__('Entfernen', 'bs-fabric')
        );
    }

    public function renderSettings(array $field): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->settingsName($field);
        $allowedTypes = is_array($settings['allowed_types'])
            ? implode(', ', $settings['allowed_types'])
            : '';

        return sprintf(
            '<p class="bs-fabric-field-setting">'
            . '<label for="%1$s-allowed_types">%2$s</label>'
            . '<input type="text" id="%1$s-allowed_types" name="%1$s[allowed_types]" value="%3$s" class="regular-text" />'
            . '</p>',
            esc_attr($name),
            esc_html__('Erlaubte MIME-Typen (kommagetrennt, leer = alle)', 'bs-fabric'),
            esc_attr($allowedTypes)
        );
    }

    public function renderValue(mixed $value): mixed
    {
        $attachmentId = $this->sanitizeValue($value);

        if ($attachmentId <= 0) {
            return null;
        }

        $url = wp_get_attachment_url($attachmentId);

        if (! is_string($url) || $url === '') {
            return null;
        }

        return [
            'id' => $attachmentId,
            'url' => esc_url($url),
            'filename' => esc_html(basename((string) get_attached_file($attachmentId))),
        ];
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
