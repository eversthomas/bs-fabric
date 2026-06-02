<?php

declare(strict_types=1);

namespace BsFabric\Frontend;

use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryCollection;
use DateTimeInterface;

final class Renderer
{
    public const SERVICE_ID = 'renderer';

    public function render(
        Entry $entry,
        string $view = 'detail',
        ?string $wrapperStart = null,
        ?string $wrapperEnd = null,
    ): string {
        $view = $this->sanitizeView($view);
        $path = $this->resolveTemplatePath($view, $entry);

        if ($path === null) {
            return '';
        }

        $html = $this->loadTemplate($path, [
            'entry' => $entry,
            'renderer' => $this,
            'view' => $view,
        ]);

        return ($wrapperStart ?? '') . $html . ($wrapperEnd ?? '');
    }

    public function renderCollection(
        EntryCollection $collection,
        string $view = 'list',
        ?string $wrapperStart = null,
        ?string $wrapperEnd = null,
    ): string {
        $view = $this->sanitizeView($view);

        if ($view === 'list') {
            $path = $this->resolveTemplatePath('list', null, $collection);

            if ($path === null) {
                return '';
            }

            $html = $this->loadTemplate($path, [
                'collection' => $collection,
                'renderer' => $this,
                'view' => $view,
            ]);

            return ($wrapperStart ?? '') . $html . ($wrapperEnd ?? '');
        }

        $html = $wrapperStart ?? '';

        foreach ($collection as $entry) {
            $html .= $this->render($entry, $view);
        }

        return $html . ($wrapperEnd ?? '');
    }

    public function getDisplayTitle(Entry $entry): string
    {
        $title = $entry->title;

        if (is_string($title) && $title !== '') {
            return $title;
        }

        return sprintf('#%d', $entry->getId());
    }

    public function getEntryLink(Entry $entry, string $view = 'detail'): string
    {
        $default = '#bs-fabric-entry-' . $entry->getId();

        /**
         * @param string $url
         * @param Entry $entryEntry
         * @param string $viewName
         */
        $url = apply_filters('bs_fabric_entry_url', $default, $entry, $view);

        return is_string($url) ? $url : $default;
    }

    public function getFirstTextValue(Entry $entry): string
    {
        foreach ($entry->getFields() as $field) {
            if ($field['type'] !== 'text') {
                continue;
            }

            $value = $entry->{$field['name']};

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    public function getFirstDescriptionValue(Entry $entry): string
    {
        foreach ($entry->getFields() as $field) {
            if (! in_array($field['type'], ['textarea', 'text'], true)) {
                continue;
            }

            $value = $entry->{$field['name']};

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    /**
     * @return array{url: string, alt: string}|null
     */
    public function getFirstImageData(Entry $entry): ?array
    {
        foreach ($entry->getFields() as $field) {
            if ($field['type'] !== 'image') {
                continue;
            }

            $value = $entry->{$field['name']};

            if (! is_array($value) || ! isset($value['url']) || ! is_string($value['url']) || $value['url'] === '') {
                continue;
            }

            return [
                'url' => $value['url'],
                'alt' => isset($value['alt']) && is_string($value['alt']) ? $value['alt'] : '',
            ];
        }

        return null;
    }

    public function renderFieldHtml(Entry $entry, string $fieldName): string
    {
        $fields = $entry->getFields();

        if (! isset($fields[$fieldName])) {
            return '';
        }

        $field = $fields[$fieldName];
        $value = $entry->{$fieldName};

        return match ($field['type']) {
            'richtext' => is_scalar($value) ? wp_kses_post((string) $value) : '',
            'image' => $this->renderImageHtml($value),
            'file' => $this->renderFileHtml($value),
            'relation' => $this->renderRelationHtml($value),
            'code' => $this->renderCodeHtml($value),
            'date' => $this->renderDateHtml($value),
            default => $this->renderScalarHtml($value),
        };
    }

    private function sanitizeView(string $view): string
    {
        $view = sanitize_key($view);

        return $view !== '' ? $view : 'detail';
    }

    private function resolveTemplatePath(
        string $view,
        ?Entry $entry = null,
        ?EntryCollection $collection = null,
    ): ?string {
        $themeDirectory = get_stylesheet_directory();
        $candidates = [
            $themeDirectory . '/bs-fabric/' . $view . '.php',
            $themeDirectory . '/bs-fabric/default/' . $view . '.php',
            BS_FABRIC_PATH . 'templates/default/' . $view . '.php',
        ];

        $resolved = null;

        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                $resolved = $candidate;

                break;
            }
        }

        if ($resolved === null) {
            return null;
        }

        /**
         * @param string $path
         * @param string $viewName
         * @param array{entry?: Entry|null, collection?: EntryCollection|null} $context
         */
        $filtered = apply_filters(
            'bs_fabric_template_path',
            $resolved,
            $view,
            [
                'entry' => $entry,
                'collection' => $collection,
            ]
        );

        if (! is_string($filtered) || ! is_readable($filtered)) {
            return $resolved;
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function loadTemplate(string $path, array $variables): string
    {
        ob_start();

        /**
         * @var Entry|null $entry
         * @var EntryCollection|null $collection
         * @var self $renderer
         * @var string $view
         */
        extract($variables, EXTR_SKIP);

        include $path;

        return (string) ob_get_clean();
    }

    private function renderScalarHtml(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            return esc_html($value ? '1' : '0');
        }

        if (is_scalar($value)) {
            return esc_html((string) $value);
        }

        if (is_array($value)) {
            $encoded = wp_json_encode($value);

            return esc_html(is_string($encoded) ? $encoded : '');
        }

        return '';
    }

    private function renderImageHtml(mixed $value): string
    {
        if (! is_array($value) || ! isset($value['url']) || ! is_string($value['url']) || $value['url'] === '') {
            return '';
        }

        $alt = isset($value['alt']) && is_string($value['alt']) ? $value['alt'] : '';

        return sprintf(
            '<img src="%s" alt="%s" class="bs-fabric-field-image" loading="lazy" />',
            esc_url($value['url']),
            esc_attr($alt)
        );
    }

    private function renderFileHtml(mixed $value): string
    {
        if (! is_array($value) || ! isset($value['url']) || ! is_string($value['url']) || $value['url'] === '') {
            return '';
        }

        $label = isset($value['filename']) && is_string($value['filename']) && $value['filename'] !== ''
            ? $value['filename']
            : __('Datei herunterladen', 'bs-fabric');

        return sprintf(
            '<a href="%s" class="bs-fabric-field-file">%s</a>',
            esc_url($value['url']),
            esc_html($label)
        );
    }

    private function renderRelationHtml(mixed $value): string
    {
        if (! $value instanceof EntryCollection) {
            return '';
        }

        if ($value->count() === 0) {
            return '';
        }

        $items = [];

        foreach ($value as $related) {
            $items[] = sprintf(
                '<li><a href="%s">%s</a></li>',
                esc_url($this->getEntryLink($related, 'detail')),
                esc_html($this->getDisplayTitle($related))
            );
        }

        return '<ul class="bs-fabric-field-relation">' . implode('', $items) . '</ul>';
    }

    private function renderCodeHtml(mixed $value): string
    {
        if (! is_scalar($value) || (string) $value === '') {
            return '';
        }

        return sprintf(
            '<pre class="bs-fabric-field-code"><code>%s</code></pre>',
            esc_html((string) $value)
        );
    }

    private function renderDateHtml(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return esc_html($value->format('d.m.Y H:i'));
        }

        return $this->renderScalarHtml($value);
    }
}
