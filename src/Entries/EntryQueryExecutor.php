<?php

declare(strict_types=1);

namespace BsFabric\Entries;

interface EntryQueryExecutor
{
    /**
     * @param array{
     *     template_id: int,
     *     wheres: list<array{field: string, value: mixed}>,
     *     with: list<string>,
     *     status: string|null,
     *     order_by: string|null,
     *     order_direction: string,
     *     limit: int|null
     * } $query
     */
    public function findByFrontendQuery(array $query): EntryCollection;

    /**
     * @param array{
     *     template_id: int,
     *     wheres: list<array{field: string, value: mixed}>,
     *     with: list<string>,
     *     status: string|null,
     *     order_by: string|null,
     *     order_direction: string,
     *     limit: int|null
     * } $query
     */
    public function countByFrontendQuery(array $query): int;

    public function getById(int $id): ?Entry;

    public function findPublishedByTemplateSlug(int $templateId, string $slug): ?Entry;
}
