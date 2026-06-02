<?php

declare(strict_types=1);

namespace BsFabric\Api;

use BsFabric\Entries\EntryQueryExecutor;
use BsFabric\Query\EntryQuery;
use BsFabric\Templates\TemplateLookup;

final class EntryQueryFactory
{
    public function __construct(
        private readonly TemplateLookup $templateRepository,
        private readonly EntryQueryExecutor $entryRepository,
    ) {
    }

    public function create(string $templateName): EntryQuery
    {
        return new EntryQuery(
            sanitize_key($templateName),
            $this->templateRepository,
            $this->entryRepository,
        );
    }
}
