<?php

declare(strict_types=1);

namespace BsFabric\Admin;

use BsFabric\Admin\Screens\EntryEditScreen;

final class ExplorerEntryFormatter
{
    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'draft' => __('Entwurf', 'bs-fabric'),
            'published' => __('Veröffentlicht', 'bs-fabric'),
            'archived' => __('Archiviert', 'bs-fabric'),
        ];
    }

    /**
     * @param list<array{
     *     id: int,
     *     title: string,
     *     template_id: int,
     *     template_label: string,
     *     status: string,
     *     created_at: string
     * }> $rows
     *
     * @return list<array<string, mixed>>
     */
    public static function formatRows(array $rows): array
    {
        $statusLabels = self::statusLabels();
        $entries = [];

        foreach ($rows as $row) {
            $createdAt = $row['created_at'];
            $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;
            $status = $row['status'];

            $entries[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'template_id' => $row['template_id'],
                'template_label' => $row['template_label'],
                'status' => $status,
                'status_label' => $statusLabels[$status] ?? $statusLabels['draft'],
                'created_at' => $createdAt,
                'created_at_label' => $timestamp !== false
                    ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)
                    : '—',
                'editUrl' => add_query_arg(
                    [
                        'page' => EntryEditScreen::SLUG,
                        'template_id' => $row['template_id'],
                        'entry_id' => $row['id'],
                    ],
                    admin_url('admin.php')
                ),
            ];
        }

        return $entries;
    }
}
