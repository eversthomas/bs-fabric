<?php

declare(strict_types=1);

namespace BsFabric\Fields\Types;

final class CodeField implements FieldTypeInterface
{
    /**
     * @var list<string>
     */
    private const ALLOWED_LANGUAGES = [
        'css',
        'html',
        'javascript',
        'json',
        'php',
        'xml',
    ];

    public function getType(): string
    {
        return 'code';
    }

    public function getLabel(): string
    {
        return __('Code', 'bs-fabric');
    }

    public function getDefaultSettings(): array
    {
        return [
            'language' => 'html',
            'line_numbers' => true,
        ];
    }

    public function sanitizeValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! is_scalar($value)) {
            return '';
        }

        return str_replace("\0", '', (string) $value);
    }

    public function renderInput(array $field, mixed $value): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->fieldName($field);
        $stringValue = is_scalar($value) ? (string) $value : '';
        $language = $this->sanitizeLanguage((string) $settings['language']);

        return sprintf(
            '<textarea class="bs-fabric-field bs-fabric-field-code" name="%s" data-language="%s" data-line-numbers="%s" rows="12">%s</textarea>',
            esc_attr($name),
            esc_attr($language),
            (bool) $settings['line_numbers'] ? '1' : '0',
            esc_textarea($stringValue)
        );
    }

    public function renderSettings(array $field): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->settingsName($field);
        $language = $this->sanitizeLanguage((string) $settings['language']);
        $optionsHtml = '';

        foreach (self::ALLOWED_LANGUAGES as $allowedLanguage) {
            $optionsHtml .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr($allowedLanguage),
                selected($language, $allowedLanguage, false),
                esc_html(strtoupper($allowedLanguage))
            );
        }

        return sprintf(
            '<p class="bs-fabric-field-setting">'
            . '<label for="%1$s-language">%2$s</label>'
            . '<select id="%1$s-language" name="%1$s[language]">%3$s</select>'
            . '</p>'
            . '<p class="bs-fabric-field-setting">'
            . '<label><input type="checkbox" name="%1$s[line_numbers]" value="1"%4$s /> %5$s</label>'
            . '</p>',
            esc_attr($name),
            esc_html__('Sprache', 'bs-fabric'),
            $optionsHtml,
            checked((bool) $settings['line_numbers'], true, false),
            esc_html__('Zeilennummern', 'bs-fabric')
        );
    }

    public function renderValue(mixed $value): mixed
    {
        if (! is_scalar($value) || $value === '') {
            return '';
        }

        return esc_html((string) $value);
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

    private function sanitizeLanguage(string $language): string
    {
        $language = sanitize_key($language);

        return in_array($language, self::ALLOWED_LANGUAGES, true) ? $language : 'html';
    }
}
