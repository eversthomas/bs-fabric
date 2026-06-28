<?php

declare(strict_types=1);

namespace BsFabric\Rest;

use BsFabric\Query\EntryQuery;

final class RestQueryParserResult
{
    private EntryQuery $query;

    /** @var list<string> */
    private array $embedRelations;

    /**
     * @param list<string> $embedRelations
     */
    public function __construct(EntryQuery $query, array $embedRelations = [])
    {
        $this->query = $query;
        $this->embedRelations = $embedRelations;
    }

    public function getQuery(): EntryQuery
    {
        return $this->query;
    }

    /**
     * @return list<string>
     */
    public function getEmbedRelations(): array
    {
        return $this->embedRelations;
    }
}
