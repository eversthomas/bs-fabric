<?php

declare(strict_types=1);

if (! function_exists('bs_fabric')) {
    /**
     * @return \BsFabric\Query\EntryQuery|\BsFabric\Entries\Entry|null
     */
    function bs_fabric(string $selector, ?int $id = null): \BsFabric\Query\EntryQuery|\BsFabric\Entries\Entry|null
    {
        return \BsFabric\Api\PublicApi::resolve($selector, $id);
    }
}
