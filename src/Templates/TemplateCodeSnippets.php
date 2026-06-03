<?php

declare(strict_types=1);

namespace BsFabric\Templates;

final class TemplateCodeSnippets
{
    /** @var list<string> */
    private const FILTERABLE_FIELD_TYPES = ['text', 'textarea', 'select', 'date'];

    /**
     * @param list<array{
     *     name: string,
     *     label: string,
     *     type: string
     * }> $assignedFields
     *
     * @return list<array{label: string, intro: string, code: string}>
     */
    public static function build(string $templateName, string $templateLabel, array $assignedFields): array
    {
        $examples = [
            self::loadAllExample($templateName, $templateLabel),
            self::loadSingleExample($templateName, $templateLabel),
        ];

        $filterField = self::pickFilterField($assignedFields);

        if ($filterField !== null) {
            $examples[] = self::filteredExample($templateName, $templateLabel, $filterField);
        }

        $relationField = self::pickRelationField($assignedFields);

        if ($relationField !== null) {
            $examples[] = self::withRelationExample($templateName, $templateLabel, $relationField);
        }

        $examples[] = self::customMarkupExample($templateName, $templateLabel);
        $examples[] = self::renderCardExample($templateName, $templateLabel);
        $examples[] = self::renderDetailExample($templateName, $templateLabel);

        return $examples;
    }

    /**
     * @return array{label: string, intro: string, code: string}
     */
    private static function loadAllExample(string $templateName, string $templateLabel): array
    {
        return [
            'label' => __('Alle Entries laden', 'bs-fabric'),
            'intro' => sprintf(
                /* translators: %s: template label */
                __('Damit holst du alle Einträge des Templates „%s“ aus der Datenbank. Du bekommst eine EntryCollection — die musst du selbst ausgeben (echo, foreach). Allein $entries = … zeigt im Browser noch nichts an.', 'bs-fabric'),
                $templateLabel
            ),
            'code' => sprintf('$entries = bs_fabric(\'%s\')->get();', $templateName),
        ];
    }

    /**
     * @return array{label: string, intro: string, code: string}
     */
    private static function loadSingleExample(string $templateName, string $templateLabel): array
    {
        return [
            'label' => __('Einen Entry laden', 'bs-fabric'),
            'intro' => sprintf(
                /* translators: %s: template label */
                __('Wenn du die ID kennst, lädst du genau einen Eintrag von „%s“. Praktisch für Detailseiten oder wenn du von einer URL mit fester ID kommst.', 'bs-fabric'),
                $templateLabel
            ),
            'code' => sprintf('$entry = bs_fabric(\'%1$s#42\');', $templateName),
        ];
    }

    /**
     * @param array{name: string, label: string, type: string} $field
     *
     * @return array{label: string, intro: string, code: string}
     */
    private static function filteredExample(string $templateName, string $templateLabel, array $field): array
    {
        $value = self::exampleFilterValue($field['type']);
        $fieldName = $field['name'];

        return [
            'label' => sprintf(
                /* translators: %s: field label */
                __('Nach Feld „%s“ filtern', 'bs-fabric'),
                $field['label']
            ),
            'intro' => sprintf(
                /* translators: 1: template label, 2: field label */
                __('So findest du nur die Einträge von „%1$s“, deren Feld „%2$s“ einen bestimmten Wert hat. Nützlich für Listen, Archive oder gefilterte Theme-Ausgaben.', 'bs-fabric'),
                $templateLabel,
                $field['label']
            ),
            'code' => sprintf(
                "\$entries = bs_fabric('%s')\n    ->where('%s', '%s')\n    ->get();",
                $templateName,
                $fieldName,
                $value
            ),
        ];
    }

    /**
     * @param array{name: string, label: string, type: string} $field
     *
     * @return array{label: string, intro: string, code: string}
     */
    private static function withRelationExample(string $templateName, string $templateLabel, array $field): array
    {
        $fieldName = $field['name'];

        return [
            'label' => sprintf(
                /* translators: %s: relation field label */
                __('Mit Relation „%s“', 'bs-fabric'),
                $field['label']
            ),
            'intro' => sprintf(
                /* translators: 1: template label, 2: relation field label */
                __('Lädt Einträge von „%1$s“ und holt verknüpfte Daten aus „%2$s“ gleich mit. Spart dir extra Abfragen, wenn du z. B. Kontakte zu einem Eintrag brauchst.', 'bs-fabric'),
                $templateLabel,
                $field['label']
            ),
            'code' => sprintf(
                "\$entries = bs_fabric('%s')\n    ->with('%s')\n    ->get();",
                $templateName,
                $fieldName
            ),
        ];
    }

    /**
     * @return array{label: string, intro: string, code: string}
     */
    private static function customMarkupExample(string $templateName, string $templateLabel): array
    {
        $code = <<<'PHP'
$entries = bs_fabric('__TEMPLATE__')->get();

foreach ($entries as $entry) {
    echo '<article class="my-__TEMPLATE__">';
    echo '<h2>' . esc_html((string) $entry->title) . '</h2>';

    foreach ($entry->getFields() as $name => $field) {
        $value = $entry->{$name};

        if ($value === null || $value === '') {
            continue;
        }

        if ($field['type'] === 'image' && is_array($value)) {
            printf(
                '<img src="%1$s" alt="%2$s" />',
                esc_url((string) ($value['url'] ?? '')),
                esc_attr((string) ($value['alt'] ?? ''))
            );
            continue;
        }

        if ($field['type'] === 'richtext' && is_scalar($value)) {
            echo wp_kses_post((string) $value);
            continue;
        }

        if (is_scalar($value)) {
            echo '<p>' . esc_html((string) $value) . '</p>';
        }
    }

    echo '</article>';
}
PHP;

        return [
            'label' => __('Eigenes Markup — alle Felder', 'bs-fabric'),
            'intro' => sprintf(
                /* translators: %s: template label */
                __('Maximale Freiheit: Du baust das HTML selbst und durchläufst alle Felder von „%s“. Kein Plugin-Layout, du entscheidest über Struktur und CSS. $entry->{$name} liefert je nach Feldtyp aufbereitete Werte (Text escaped, Richtext als HTML, Bilder als Array).', 'bs-fabric'),
                $templateLabel
            ),
            'code' => str_replace('__TEMPLATE__', $templateName, $code),
        ];
    }

    /**
     * @return array{label: string, intro: string, code: string}
     */
    private static function renderCardExample(string $templateName, string $templateLabel): array
    {
        return [
            'label' => __('Als Card-Teaser (render)', 'bs-fabric'),
            'intro' => sprintf(
                /* translators: %s: template label */
                __('Shortcut mit fertigem HTML: card zeigt pro Eintrag von „%s“ nur Titel, erstes Text- und erstes Bildfeld — nicht alle Felder. Gut für Übersichten. Den äußeren Container (div) legst du selbst fest.', 'bs-fabric'),
                $templateLabel
            ),
            'code' => sprintf(
                "echo bs_fabric('%s')\n    ->get()\n    ->render('card', '<div class=\"cards\">', '</div>');",
                $templateName
            ),
        ];
    }

    /**
     * @return array{label: string, intro: string, code: string}
     */
    private static function renderDetailExample(string $templateName, string $templateLabel): array
    {
        return [
            'label' => __('Als Detail-Ansicht (render)', 'bs-fabric'),
            'intro' => sprintf(
                /* translators: %s: template label */
                __('Shortcut für Einzelansichten: detail gibt alle nicht-leeren Felder von „%s“ mit Label aus (dl/dt/dd-Markup). Leere Felder werden übersprungen. View-Template kann im Theme unter /bs-fabric/detail.php überschrieben werden.', 'bs-fabric'),
                $templateLabel
            ),
            'code' => sprintf(
                "echo bs_fabric('%s#42')->render('detail');",
                $templateName
            ),
        ];
    }

    /**
     * @param list<array{name: string, label: string, type: string}> $assignedFields
     *
     * @return array{name: string, label: string, type: string}|null
     */
    private static function pickFilterField(array $assignedFields): ?array
    {
        foreach ($assignedFields as $field) {
            if (in_array($field['type'], self::FILTERABLE_FIELD_TYPES, true)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param list<array{name: string, label: string, type: string}> $assignedFields
     *
     * @return array{name: string, label: string, type: string}|null
     */
    private static function pickRelationField(array $assignedFields): ?array
    {
        foreach ($assignedFields as $field) {
            if ($field['type'] === 'relation') {
                return $field;
            }
        }

        return null;
    }

    private static function exampleFilterValue(string $type): string
    {
        return match ($type) {
            'date' => '2026-01-01',
            'select' => 'beispiel',
            default => 'Müller',
        };
    }
}
