<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Entries;

use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryCollection;
use BsFabric\Fields\FieldRegistry;
use BsFabric\Relations\RelationRepository;
use BsFabric\Tests\Unit\TestCase;

final class EntryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Brain\Monkey\Functions\when('apply_filters')->alias(
            static fn (string $hook, mixed $value): mixed => $value
        );
        \Brain\Monkey\Functions\when('__')->returnArg(1);
    }
    public function testMagicGetReturnsMetaAndFieldValues(): void
    {
        \Brain\Monkey\Functions\when('esc_html')->alias(static fn (string $value): string => $value);

        $entry = $this->createEntry();

        $this->assertSame(42, $entry->id);
        $this->assertSame('Published Entry', $entry->title);
        $this->assertSame('published', $entry->status);
        $this->assertSame('Hello World', $entry->name);
    }

    public function testMagicGetReturnsEntryCollectionForRelationField(): void
    {
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('prepare')->andReturnUsing(
            static fn (string $query, mixed ...$args): string => $query
        );
        $wpdb->shouldReceive('get_col')->once()->andReturn([]);
        $GLOBALS['wpdb'] = $wpdb;

        $entry = new Entry(
            [
                'id' => 5,
                'template_id' => 1,
                'title' => 'Main',
                'status' => 'draft',
                'language' => 'de',
                'created_by' => null,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ],
            [
                'contacts' => [
                    'id' => 99,
                    'name' => 'contacts',
                    'label' => 'Contacts',
                    'type' => 'relation',
                    'settings' => ['multiple' => true],
                ],
            ],
            [],
            new FieldRegistry(),
            new RelationRepository(),
            static fn (int $id): ?Entry => null,
        );

        $collection = $entry->contacts;

        $this->assertInstanceOf(EntryCollection::class, $collection);
        $this->assertCount(0, $collection);
    }

    public function testToArrayIncludesMetaAndFieldValues(): void
    {
        $entry = $this->createEntry();
        $array = $entry->toArray();

        $this->assertSame(42, $array['id']);
        $this->assertSame('Hello World', $array['name']);
        $this->assertSame('published', $array['status']);
    }

    public function testToJsonEncodesArrayRepresentation(): void
    {
        \Brain\Monkey\Functions\when('wp_json_encode')->alias(
            static fn (mixed $data): string => (string) json_encode($data)
        );

        $entry = $this->createEntry();
        $json = $entry->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame(42, $decoded['id']);
        $this->assertSame('Hello World', $decoded['name']);
    }

    private function createEntry(): Entry
    {
        $registry = new FieldRegistry();

        return new Entry(
            [
                'id' => 42,
                'template_id' => 7,
                'title' => 'Published Entry',
                'status' => 'published',
                'language' => 'de',
                'created_by' => 1,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-02 00:00:00',
            ],
            [
                'name' => [
                    'id' => 3,
                    'name' => 'name',
                    'label' => 'Name',
                    'type' => 'text',
                    'settings' => [],
                ],
            ],
            [
                'name' => 'Hello World',
            ],
            $registry,
            new RelationRepository(),
            null,
        );
    }
}
