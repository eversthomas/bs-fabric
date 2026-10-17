<?php

declare(strict_types=1);

namespace BsFabric\Frontend;

use BsFabric\Entries\Entry;

interface EntryUrlResolver
{
    public function urlFor(Entry $entry): ?string;
}
