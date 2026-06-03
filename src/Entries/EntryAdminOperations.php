<?php

declare(strict_types=1);

namespace BsFabric\Entries;

interface EntryAdminOperations
{
    /**
     * @param array{
     *     page?: int,
     *     per_page?: int,
     *     search?: string,
     *     template_id?: int,
     *     status?: string,
     *     sort_field?: string,
     *     sort_direction?: string
     * } $args
     *
     * @return array{
     *     rows: list<array{
     *         id: int,
     *         title: string,
     *         template_id: int,
     *         template_label: string,
     *         status: string,
     *         created_at: string
     *     }>,
     *     total: int,
     *     total_pages: int
     * }
     */
    public function queryExplorerRows(array $args): array;

    public function getTemplateIdForEntry(int $id): ?int;

    public function updateStatus(int $id, string $status): bool;

    public function delete(int $id): bool;

    public function duplicate(int $id): int;
}
