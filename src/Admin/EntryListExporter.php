<?php

declare(strict_types=1);

namespace BsFabric\Admin;

use BsFabric\Entries\Entry;

final class EntryListExporter
{
    /**
     * @param list<Entry> $entries
     * @param list<array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>
     * }> $listColumns
     */
    public function toCsv(array $entries, array $listColumns): string
    {
        $rows = [];
        $header = [__('ID', 'bs-fabric'), __('Titel', 'bs-fabric')];

        foreach ($listColumns as $field) {
            $header[] = (string) $field['label'];
        }

        if ($listColumns !== []) {
            $header[] = __('Status', 'bs-fabric');
            $header[] = __('Erstellt am', 'bs-fabric');
        }

        $rows[] = $header;

        foreach ($entries as $entry) {
            $row = [
                (string) $entry->getId(),
                EntryListFormatter::title($entry),
            ];

            foreach ($listColumns as $field) {
                $row[] = EntryListFormatter::fieldValue($entry, $field, false);
            }

            if ($listColumns !== []) {
                $row[] = EntryListFormatter::statusLabel((string) $entry->status);
                $row[] = EntryListFormatter::date((string) $entry->created_at, true);
            }

            $rows[] = $row;
        }

        $lines = [];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map([$this, 'escapeCell'], $row));
        }

        return "\xEF\xBB\xBF" . implode("\r\n", $lines);
    }

    private function escapeCell(string $value): string
    {
        // CSV/Excel-Formula-Injection: Werte, die von Spreadsheet-Programmen als Formel
        // interpretiert werden könnten, per führendem Apostroph entschärfen (OWASP-Empfehlung).
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            $value = "'" . $value;
        }

        if (! str_contains($value, ',') && ! str_contains($value, '"') && ! str_contains($value, "\n") && ! str_contains($value, "\r")) {
            return $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }
}
