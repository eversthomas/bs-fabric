<?php

declare(strict_types=1);

namespace BsFabric\Entries;

use ArrayIterator;
use BsFabric\Frontend\Renderer;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, Entry>
 */
final class EntryCollection implements Countable, IteratorAggregate
{
    /**
     * @param list<Entry> $entries
     */
    public function __construct(
        private array $entries = [],
    ) {
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->entries);
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function first(): ?Entry
    {
        return $this->entries[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (Entry $entry): array => $entry->toArray(),
            $this->entries
        );
    }

    /**
     * @return list<Entry>
     */
    public function all(): array
    {
        return $this->entries;
    }

    public function render(
        string $view = 'list',
        ?string $wrapperStart = null,
        ?string $wrapperEnd = null,
    ): string {
        return (new Renderer())->renderCollection($this, $view, $wrapperStart, $wrapperEnd);
    }
}
