<?php

declare(strict_types=1);

namespace BsFabric\Rest;

use BsFabric\Api\EntryQueryFactory;
use BsFabric\Core\Capabilities;
use BsFabric\Query\EntryQuery;
use WP_REST_Request;

final class RestQueryParser
{
    private EntryQueryFactory $queryFactory;

    public function __construct(EntryQueryFactory $queryFactory)
    {
        $this->queryFactory = $queryFactory;
    }

    public function fromRequest(WP_REST_Request $request): RestQueryParserResult
    {
        $templateName = sanitize_key((string) $request->get_param('template'));
        $query = $this->queryFactory->create($templateName);
        $embedRelations = $this->parseEmbedRelations($request);

        $query = $this->applyWhereFilters($query, $request);
        $query = $this->applyOrdering($query, $request);
        $query = $this->applyLimit($query, $request);
        $query = $this->applyStatusFilter($query, $request);
        $query = $this->applyRelationEmbed($query, $embedRelations);

        return new RestQueryParserResult($query, $embedRelations);
    }

    /**
     * @return list<string>
     */
    public function parseEmbedRelations(WP_REST_Request $request): array
    {
        $with = trim((string) ($request->get_param('with') ?? ''));

        if ($with === '') {
            return [];
        }

        $relations = [];

        foreach (explode(',', $with) as $part) {
            $fieldName = sanitize_key(trim($part));

            if ($fieldName === '') {
                continue;
            }

            $relations[] = $fieldName;
        }

        return array_values(array_unique($relations));
    }

    /**
     * @param list<string> $embedRelations
     */
    private function applyRelationEmbed(EntryQuery $query, array $embedRelations): EntryQuery
    {
        foreach ($embedRelations as $fieldName) {
            $query = $query->with($fieldName);
        }

        return $query;
    }

    private function applyWhereFilters(EntryQuery $query, WP_REST_Request $request): EntryQuery
    {
        $where = $request->get_param('where');

        if (! is_array($where)) {
            return $query;
        }

        foreach ($where as $field => $value) {
            $fieldName = sanitize_key((string) $field);

            if ($fieldName === '') {
                continue;
            }

            $query = $query->where($fieldName, $this->normalizeWhereValue($value));
        }

        return $query;
    }

    private function applyOrdering(EntryQuery $query, WP_REST_Request $request): EntryQuery
    {
        $orderBy = sanitize_key((string) ($request->get_param('orderby') ?? 'created_at'));

        if ($orderBy === '') {
            $orderBy = 'created_at';
        }

        $order = strtolower((string) ($request->get_param('order') ?? 'desc'));

        return $query->orderBy($orderBy, $order === 'asc' ? 'ASC' : 'DESC');
    }

    private function applyLimit(EntryQuery $query, WP_REST_Request $request): EntryQuery
    {
        $perPage = absint($request->get_param('per_page'));

        if ($perPage <= 0) {
            $perPage = 10;
        }

        return $query->limit(min($perPage, 100));
    }

    private function applyStatusFilter(EntryQuery $query, WP_REST_Request $request): EntryQuery
    {
        $status = sanitize_key((string) ($request->get_param('status') ?? 'published'));
        $canManageEntries = current_user_can(Capabilities::MANAGE_ENTRIES);

        if ($status === 'any') {
            return $canManageEntries ? $query->withAnyStatus() : $query->status('published');
        }

        if ($status === 'draft' || $status === 'archived') {
            return $canManageEntries ? $query->status($status) : $query->status('published');
        }

        return $query->status('published');
    }

    private function normalizeWhereValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return $value;
        }

        return '';
    }
}
