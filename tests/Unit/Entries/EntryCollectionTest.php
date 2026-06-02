<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Entries;

use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryCollection;
use BsFabric\Fields\FieldRegistry;
use BsFabric\Relations\RelationRepository;
use BsFabric\Tests\Unit\TestCase;

final class EntryCollectionTest extends TestCase
{
    public function testIteratorReturnsAllEntries(): void
    {
        $collection = new EntryCollection([
            $this->createEntry(1),
            $this->createEntry(2),
        ]);

        $ids = [];

        foreach ($collection as $entry) {
            $ids[] = $entry->id;
        }

        $this->assertSame([1, 2], $ids);
    }

    public function testCountReturnsNumberOfEntries(): void
    {
        $collection = new EntryCollection([
            $this->createEntry(1),
            $this->createEntry(2),
            $this->createEntry(3),
        ]);

        $this->assertCount(3, $collection);
    }

    public function testFirstReturnsFirstEntryOrNull(): void
    {
        $empty = new EntryCollection();
        $this->assertNull($empty->first());

        $collection = new EntryCollection([
            $this->createEntry(10),
            $this->createEntry(20),
        ]);

        $this->assertSame(10, $collection->first()?->id);
    }

    public function testToArrayReturnsEntryArrays(): void
    {
        $collection = new EntryCollection([
            $this->createEntry(5),
        ]);

        $array = $collection->toArray();

        $this->assertCount(1, $array);
        $this->assertSame(5, $array[0]['id']);
        $this->assertSame('draft', $array[0]['status']);
    }

    private function createEntry(int $id): Entry
    {
        return new Entry(
            [
                'id' => $id,
                'template_id' => 1,
                'title' => 'Entry ' . $id,
                'status' => 'draft',
                'language' => 'de',
                'created_by' => null,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ],
            [],
            [],
            new FieldRegistry(),
            new RelationRepository(),
            null,
        );
    }
}
