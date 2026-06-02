<?php

declare(strict_types=1);

namespace BsFabric\Frontend;

use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryCollection;

final class Renderer
{
    public const SERVICE_ID = 'renderer';

    public function renderEntry(
        Entry $entry,
        string $view = 'detail',
        ?string $wrapperStart = null,
        ?string $wrapperEnd = null,
    ): string {
        // Session 10: View-Templates aus Theme/Plugin laden.
        unset($entry, $view, $wrapperStart, $wrapperEnd);

        return '';
    }

    public function renderCollection(
        EntryCollection $collection,
        string $view = 'list',
        ?string $wrapperStart = null,
        ?string $wrapperEnd = null,
    ): string {
        // Session 10: View-Templates aus Theme/Plugin laden.
        unset($collection, $view, $wrapperStart, $wrapperEnd);

        return '';
    }
}
