<?php

declare(strict_types=1);

namespace BsFabric\Fields\Types;

/**
 * Verweis auf WordPress-Inhalte (Beiträge, Seiten, Medien) — nicht Fabric-Entries ({@see RelationField}).
 */
final class ReferenceField implements FieldTypeInterface
{
    use SanitizesFieldSettings;

    public function getType(): string
    {
        return 'reference';
    }

    public function getLabel(): string
    {
        return __('WP-Referenz', 'bs-fabric');
    }

    public function getDefaultSettings(): array
    {
        return [
            'post_types' => ['post', 'page'],
            'multiple' => false,
        ];
    }

    public function sanitizeValue(mixed $value): mixed
    {
        $wasList = is_array($value) && ! isset($value['id']) && ! isset($value['ID']);
        $ids = $this->normalizeIds($value);

        if ($ids === []) {
            return null;
        }

        return $wasList ? $ids : $ids[0];
    }

    public function renderInput(array $field, mixed $value): string
    {
        $settings = $this->mergeSettings($field);
        $multiple = ! empty($settings['multiple']);
        $postTypes = $this->normalizePostTypes($settings['post_types'] ?? ['post', 'page']);
        $ids = $this->normalizeIds($value);
        $name = $this->fieldName($field);
        $inputName = $multiple ? $name . '[]' : $name;
        $listId = 'bs-fabric-reference-list-' . sanitize_key((string) ($field['name'] ?? ''));

        $itemsHtml = '';

        foreach ($ids as $postId) {
            $post = get_post($postId);

            if (! $post instanceof \WP_Post) {
                continue;
            }

            $label = $this->formatPostLabel($post);

            $itemsHtml .= sprintf(
                '<li class="bs-fabric-reference-item" data-post-id="%1$d">'
                . '<span class="bs-fabric-reference-handle">⋮⋮</span>'
                . '<span class="bs-fabric-reference-label">%2$s</span>'
                . '<button type="button" class="button-link bs-fabric-reference-remove">%3$s</button>'
                . '<input type="hidden" name="%4$s" value="%1$d" />'
                . '</li>',
                $postId,
                esc_html($label),
                esc_html__('Entfernen', 'bs-fabric'),
                esc_attr($inputName)
            );
        }

        if (! $multiple && $itemsHtml === '') {
            $itemsHtml = sprintf(
                '<input type="hidden" name="%s" value="0" class="bs-fabric-reference-empty" />',
                esc_attr($name)
            );
        }

        $requiredAttr = ! empty($field['_template_required']) ? ' data-required="1"' : '';

        return sprintf(
            '<div class="bs-fabric-reference-field"%1$s data-field-name="%2$s" data-post-types="%3$s" data-multiple="%4$s">'
            . '<div class="bs-fabric-reference-search-wrap">'
            . '<input type="search" class="bs-fabric-reference-search regular-text" placeholder="%5$s" autocomplete="off" />'
            . '<ul class="bs-fabric-reference-results"></ul>'
            . '</div>'
            . '<ul id="%6$s" class="bs-fabric-reference-selected">%7$s</ul>'
            . '<p class="description">%8$s</p>'
            . '</div>',
            $requiredAttr,
            esc_attr((string) ($field['name'] ?? '')),
            esc_attr(implode(',', $postTypes)),
            $multiple ? '1' : '0',
            esc_attr__('WordPress-Inhalt suchen…', 'bs-fabric'),
            esc_attr($listId),
            $itemsHtml,
            $multiple
                ? esc_html__('Ausgewählte Inhalte per Drag & Drop sortieren.', 'bs-fabric')
                : esc_html__('Ein Beitrag, eine Seite oder ein Medium auswählen.', 'bs-fabric')
        );
    }

    public function renderSettings(array $field): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->settingsName($field);
        $selectedTypes = $this->normalizePostTypes($settings['post_types'] ?? ['post', 'page']);

        $checkboxes = '';

        foreach ([
            'post' => __('Beiträge', 'bs-fabric'),
            'page' => __('Seiten', 'bs-fabric'),
            'attachment' => __('Medien', 'bs-fabric'),
        ] as $type => $label) {
            $checkboxes .= sprintf(
                '<label class="bs-fabric-field-setting-inline">'
                . '<input type="checkbox" name="%1$s[post_types][]" value="%2$s"%3$s /> %4$s'
                . '</label> ',
                esc_attr($name),
                esc_attr($type),
                checked(in_array($type, $selectedTypes, true), true, false),
                esc_html($label)
            );
        }

        $extras = array_values(array_filter(
            $selectedTypes,
            static fn (string $type): bool => ! in_array($type, ['post', 'page', 'attachment'], true)
        ));

        return sprintf(
            '<fieldset class="bs-fabric-field-setting-group">'
            . '<legend>%1$s</legend>'
            . '<p class="bs-fabric-field-setting"><span class="bs-fabric-field-setting-label">%2$s</span><br />%3$s</p>'
            . '<p class="bs-fabric-field-setting">'
            . '<label for="%4$s-extra_types">%5$s</label>'
            . '<input type="text" id="%4$s-extra_types" name="%4$s[extra_post_types]" value="%6$s" class="regular-text" placeholder="product,event" />'
            . '</p>'
            . '<p class="bs-fabric-field-setting">'
            . '<label><input type="checkbox" name="%4$s[multiple]" value="1"%7$s /> %8$s</label>'
            . '</p>'
            . '<p class="description">%9$s</p>'
            . '</fieldset>',
            esc_html__('WP-Referenz', 'bs-fabric'),
            esc_html__('Erlaubte Inhaltstypen', 'bs-fabric'),
            $checkboxes,
            esc_attr($name),
            esc_html__('Weitere Post-Types (kommagetrennt)', 'bs-fabric'),
            esc_attr(implode(',', $extras)),
            checked(! empty($settings['multiple']), true, false),
            esc_html__('Mehrfachauswahl', 'bs-fabric'),
            esc_html__('Verweist auf WordPress-Inhalte — nicht auf Fabric-Entries (dafür: Relation).', 'bs-fabric')
        );
    }

    public function renderValue(mixed $value): mixed
    {
        $wasList = is_array($value);
        $ids = $this->normalizeIds($value);

        if ($ids === []) {
            return null;
        }

        $objects = [];

        foreach ($ids as $postId) {
            $object = $this->hydratePost($postId);

            if ($object !== null) {
                $objects[] = $object;
            }
        }

        if ($objects === []) {
            return null;
        }

        // Skalar gespeichert (Einzelauswahl) → Objekt; JSON-Liste → Liste von Objekten.
        return $wasList ? $objects : $objects[0];
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    protected function sanitizeTypeSettings(array $settings): array
    {
        $settings = $this->sanitizeBoolSetting($settings, 'multiple');

        $postTypes = [];

        if (isset($settings['post_types']) && is_array($settings['post_types'])) {
            foreach ($settings['post_types'] as $type) {
                $key = sanitize_key((string) $type);

                if ($key !== '') {
                    $postTypes[] = $key;
                }
            }
        }

        if (isset($settings['extra_post_types']) && is_string($settings['extra_post_types'])) {
            foreach (explode(',', $settings['extra_post_types']) as $type) {
                $key = sanitize_key(trim($type));

                if ($key !== '') {
                    $postTypes[] = $key;
                }
            }
        }

        unset($settings['extra_post_types']);

        $postTypes = array_values(array_unique($postTypes));
        $settings['post_types'] = $postTypes !== [] ? $postTypes : ['post', 'page'];

        return $settings;
    }

    /**
     * @return list<int>
     */
    private function normalizeIds(mixed $value): array
    {
        if ($value === null || $value === '' || $value === [] || $value === 0 || $value === '0') {
            return [];
        }

        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        $rawIds = is_array($value) ? $value : [$value];
        $ids = [];

        foreach ($rawIds as $item) {
            if (is_array($item)) {
                $item = $item['id'] ?? $item['ID'] ?? 0;
            }

            $id = absint($item);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array{id: int, title: string, url: string, type: string, status: string}|null
     */
    private function hydratePost(int $postId): ?array
    {
        $post = get_post($postId);

        if (! $post instanceof \WP_Post) {
            return null;
        }

        $url = get_permalink($post);

        if ($post->post_type === 'attachment') {
            $attachmentUrl = wp_get_attachment_url($postId);
            $url = is_string($attachmentUrl) ? $attachmentUrl : '';
        }

        return [
            'id' => $postId,
            'title' => $this->formatPostLabel($post),
            'url' => is_string($url) ? $url : '',
            'type' => $post->post_type,
            'status' => $post->post_status,
        ];
    }

    private function formatPostLabel(\WP_Post $post): string
    {
        $title = $post->post_title !== '' ? $post->post_title : sprintf('#%d', $post->ID);
        $typeObject = get_post_type_object($post->post_type);
        $typeLabel = $typeObject instanceof \WP_Post_Type ? $typeObject->labels->singular_name : $post->post_type;

        return sprintf('%s (%s)', $title, $typeLabel);
    }

    /**
     * @param mixed $postTypes
     *
     * @return list<string>
     */
    private function normalizePostTypes(mixed $postTypes): array
    {
        if (is_string($postTypes)) {
            $postTypes = array_map('trim', explode(',', $postTypes));
        }

        if (! is_array($postTypes)) {
            return ['post', 'page'];
        }

        $normalized = [];

        foreach ($postTypes as $type) {
            $key = sanitize_key((string) $type);

            if ($key !== '') {
                $normalized[] = $key;
            }
        }

        $normalized = array_values(array_unique($normalized));

        return $normalized !== [] ? $normalized : ['post', 'page'];
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
