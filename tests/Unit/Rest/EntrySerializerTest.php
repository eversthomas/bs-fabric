<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Rest;

use BsFabric\Entries\Entry;
use BsFabric\Fields\FieldRegistry;
use BsFabric\Relations\RelationRepository;
use BsFabric\Rest\EntrySerializer;
use BsFabric\Tests\Unit\TestCase;

final class EntrySerializerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Brain\Monkey\Functions\when('apply_filters')->alias(
            static fn (string $hook, mixed $value): mixed => $value
        );
        \Brain\Monkey\Functions\when('sanitize_key')->alias(
            static fn (string $value): string => strtolower(preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '')
        );
        \Brain\Monkey\Functions\when('rest_url')->alias(
            static fn (string $path): string => 'https://example.test/wp-json/' . ltrim($path, '/')
        );
        \Brain\Monkey\Functions\when('esc_html')->alias(
            static fn (string $value): string => $value
        );
    }

    public function testRelationWithoutEmbedSerializesAsIdArray(): void
    {
        $entry = $this->createEntryWithRelations();
        $serializer = new EntrySerializer([]);
        $data = $serializer->serialize($entry, 'einrichtung');

        $this->assertSame([12, 15], $data['ansprechpartner']);
    }

    public function testRelationWithEmbedSerializesAsNestedObjects(): void
    {
        $entry = $this->createEntryWithRelations();
        $serializer = new EntrySerializer(['ansprechpartner']);
        $data = $serializer->serialize($entry, 'einrichtung');

        $this->assertIsArray($data['ansprechpartner']);
        $this->assertCount(2, $data['ansprechpartner']);
        $this->assertSame(12, $data['ansprechpartner'][0]['id']);
        $this->assertSame('person', $data['ansprechpartner'][0]['template']);
        $this->assertSame('Anna Müller', $data['ansprechpartner'][0]['name']);
        $this->assertSame([15], $data['ansprechpartner'][0]['ansprechpartner']);
    }

    private function createEntryWithRelations(): Entry
    {
        $registry = new FieldRegistry();

        $personOne = new Entry(
            [
                'id' => 12,
                'template_id' => 2,
                'title' => 'Anna Müller',
                'status' => 'published',
                'language' => 'de',
                'created_by' => null,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ],
            [
                'name' => [
                    'id' => 20,
                    'name' => 'name',
                    'label' => 'Name',
                    'type' => 'text',
                    'settings' => [],
                ],
                'ansprechpartner' => [
                    'id' => 21,
                    'name' => 'ansprechpartner',
                    'label' => 'Ansprechpartner',
                    'type' => 'relation',
                    'settings' => [
                        'template' => 'person',
                        'multiple' => true,
                    ],
                ],
            ],
            [
                'name' => 'Anna Müller',
            ],
            $registry,
            $this->mockRelationRepository([15]),
            static fn (int $id): ?Entry => null,
        );

        $personTwo = new Entry(
            [
                'id' => 15,
                'template_id' => 2,
                'title' => 'Bob Schmidt',
                'status' => 'published',
                'language' => 'de',
                'created_by' => null,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ],
            [
                'name' => [
                    'id' => 22,
                    'name' => 'name',
                    'label' => 'Name',
                    'type' => 'text',
                    'settings' => [],
                ],
            ],
            [
                'name' => 'Bob Schmidt',
            ],
            $registry,
            new RelationRepository(),
            null,
        );

        return new Entry(
            [
                'id' => 42,
                'template_id' => 1,
                'title' => 'Stadtwerke',
                'status' => 'published',
                'language' => 'de',
                'created_by' => null,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-02 00:00:00',
            ],
            [
                'name' => [
                    'id' => 10,
                    'name' => 'name',
                    'label' => 'Name',
                    'type' => 'text',
                    'settings' => [],
                ],
                'ansprechpartner' => [
                    'id' => 11,
                    'name' => 'ansprechpartner',
                    'label' => 'Ansprechpartner',
                    'type' => 'relation',
                    'settings' => [
                        'template' => 'person',
                        'multiple' => true,
                    ],
                ],
            ],
            [
                'name' => 'Stadtwerke',
            ],
            $registry,
            $this->mockRelationRepository([12, 15]),
            static function (int $id) use ($personOne, $personTwo): ?Entry {
                if ($id === 12) {
                    return $personOne;
                }

                if ($id === 15) {
                    return $personTwo;
                }

                return null;
            },
        );
    }

    /**
     * @param list<int> $relatedIds
     */
    private function mockRelationRepository(array $relatedIds): RelationRepository
    {
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('prepare')->andReturnUsing(
            static fn (string $query, mixed ...$args): string => $query
        );
        $wpdb->shouldReceive('get_col')->andReturn(array_map('strval', $relatedIds));
        $GLOBALS['wpdb'] = $wpdb;

        return new RelationRepository();
    }
}
