<?php

declare(strict_types=1);

namespace BsFabric\Admin;

use BsFabric\Entries\Entry;

final class EntryListFormatter
{
    public static function title(Entry $entry): string
    {
        if ($entry->title !== null && $entry->title !== '') {
            return (string) $entry->title;
        }

        return sprintf('#%d', $entry->getId());
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'published' => __('Veröffentlicht', 'bs-fabric'),
            'archived' => __('Archiviert', 'bs-fabric'),
            default => __('Entwurf', 'bs-fabric'),
        };
    }

    /**
     * @param array{name?: string, type?: string} $field
     */
    public static function fieldValue(Entry $entry, array $field, bool $truncate = true): string
    {
        $fieldName = (string) ($field['name'] ?? '');

        if ($fieldName === '') {
            return '—';
        }

        $value = $entry->{$fieldName};

        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? __('Ja', 'bs-fabric') : __('Nein', 'bs-fabric');
        }

        if (is_array($value)) {
            if (isset($value['formatted']) && is_string($value['formatted']) && $value['formatted'] !== '') {
                $formatted = $value['formatted'];

                if ($truncate && strlen($formatted) > 80) {
                    return substr($formatted, 0, 77) . '…';
                }

                return $formatted;
            }

            if (isset($value['city']) && is_string($value['city']) && $value['city'] !== '') {
                return $value['city'];
            }

            if (isset($value['url']) && is_string($value['url'])) {
                return $value['url'];
            }

            if (isset($value['label']) && is_string($value['label'])) {
                return $value['label'];
            }

            $labels = [];

            foreach ($value as $item) {
                if (is_array($item) && isset($item['title']) && is_string($item['title'])) {
                    $labels[] = $item['title'];
                }
            }

            if ($labels !== []) {
                return implode(', ', $labels);
            }

            return '…';
        }

        $stringValue = is_scalar($value) ? (string) $value : '';

        if ($stringValue === '') {
            return '—';
        }

        if ($truncate && strlen($stringValue) > 80) {
            return substr($stringValue, 0, 77) . '…';
        }

        return $stringValue;
    }

    public static function date(string $date, bool $forExport = false): string
    {
        if ($date === '') {
            return '—';
        }

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return $date;
        }

        if ($forExport) {
            return wp_date('Y-m-d H:i:s', $timestamp);
        }

        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }
}
