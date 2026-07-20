<?php

declare(strict_types=1);

namespace BsFabric\Admin\Components;

use BsFabric\Fields\FieldRegistry;
use BsFabric\Fields\Types\FieldTypeInterface;

final class FieldTypeCatalog
{
    /** @var array<string, array{group: string, hint: string, icon: string}> */
    private const META = [
        'text' => ['group' => 'Basis', 'hint' => 'Einzeilige Texteingabe', 'icon' => 't_text'],
        'textarea' => ['group' => 'Basis', 'hint' => 'Mehrzeiliger Text', 'icon' => 't_textarea'],
        'richtext' => ['group' => 'Basis', 'hint' => 'Formatierter Editor', 'icon' => 't_richtext'],
        'select' => ['group' => 'Basis', 'hint' => 'Auswahl aus Optionen', 'icon' => 't_select'],
        'boolean' => ['group' => 'Basis', 'hint' => 'Ja/Nein-Schalter', 'icon' => 't_boolean'],
        'number' => ['group' => 'Basis', 'hint' => 'Numerischer Wert mit Min/Max', 'icon' => 't_number'],
        'date' => ['group' => 'Basis', 'hint' => 'Datum und optional Uhrzeit', 'icon' => 't_date'],
        'image' => ['group' => 'Medien', 'hint' => 'Einzelbild aus Mediathek', 'icon' => 't_media'],
        'file' => ['group' => 'Medien', 'hint' => 'Datei aus Mediathek', 'icon' => 't_media'],
        'relation' => ['group' => 'Struktur', 'hint' => 'Verweis auf andere Einträge', 'icon' => 't_relation'],
        'address' => ['group' => 'Struktur', 'hint' => 'Straße, PLZ, Ort, Land — optional mit Geocoding', 'icon' => 't_address'],
        'code' => ['group' => 'Entwickler', 'hint' => 'Code mit Syntaxhighlighting', 'icon' => 't_code'],
    ];

    /** @var array<string, string> */
    private const GROUP_ORDER = [
        'Basis' => 'Basis',
        'Medien' => 'Medien',
        'Struktur' => 'Struktur',
        'Entwickler' => 'Entwickler',
    ];

    public static function iconFor(string $type): string
    {
        return self::META[$type]['icon'] ?? 't_text';
    }

    public static function hintFor(string $type): string
    {
        return self::META[$type]['hint'] ?? '';
    }

    public static function groupFor(string $type): string
    {
        return self::META[$type]['group'] ?? __('Sonstige', 'bs-fabric');
    }

    /**
     * @return array<string, list<array{type: string, label: string, hint: string, icon: string}>>
     */
    public static function grouped(FieldRegistry $registry): array
    {
        $groups = [];

        foreach (self::GROUP_ORDER as $groupLabel) {
            $groups[$groupLabel] = [];
        }

        foreach ($registry->all() as $fieldType) {
            $type = $fieldType->getType();
            $group = self::groupFor($type);

            if (! isset($groups[$group])) {
                $groups[$group] = [];
            }

            $groups[$group][] = [
                'type' => $type,
                'label' => $fieldType->getLabel(),
                'hint' => self::hintFor($type),
                'icon' => self::iconFor($type),
            ];
        }

        return array_filter(
            $groups,
            static fn (array $items): bool => $items !== []
        );
    }
}
