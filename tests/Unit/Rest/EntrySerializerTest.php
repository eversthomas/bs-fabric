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

    public function testFieldMarkedRestHiddenIsExcludedFromPayload(): void
    {
        $entry = new Entry(
            [
                'id' => 7,
                'template_id' => 3,
                'title' => 'Interner Datensatz',
                'status' => 'published',
                'language' => 'de',
                'created_by' => null,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ],
            [
                'public_note' => [
                    'id' => 30,
                    'name' => 'public_note',
                    'label' => 'Öffentliche Notiz',
                    'type' => 'text',
                    'settings' => [],
                    'rest_hidden' => false,
                ],
                'internal_note' => [
                    'id' => 31,
                    'name' => 'internal_note',
                    'label' => 'Interne Notiz',
                    'type' => 'text',
                    'settings' => [],
                    'rest_hidden' => true,
                ],
            ],
            [
                'public_note' => 'Für alle sichtbar',
                'internal_note' => 'Nur intern',
            ],
            new FieldRegistry(),
            new RelationRepository(),
            null,
        );

        $data = (new EntrySerializer([]))->serialize($entry, 'notiz');

        self::assertArrayHasKey('public_note', $data);
        self::assertArrayNotHasKey('internal_note', $data);
    }

    private function createEntryWithRelations(): Entry
    {
        $registry = new FieldRegistry();
        $this->installRelationIdMap([
            12 => [15],
            42 => [12, 15],
        ]);

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
            new RelationRepository(),
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
            new RelationRepository(),
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
     * @param array<int, list<int>> $idsByFromEntryId
     */
    private function installRelationIdMap(array $idsByFromEntryId): void
    {
        $lastFromEntryId = 0;

        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('prepare')->andReturnUsing(
            static function (string $query, mixed ...$args) use (&$lastFromEntryId): string {
                $lastFromEntryId = (int) ($args[0] ?? 0);

                return $query;
            }
        );
        $wpdb->shouldReceive('get_col')->andReturnUsing(
            static function () use (&$lastFromEntryId, $idsByFromEntryId): array {
                $ids = $idsByFromEntryId[$lastFromEntryId] ?? [];

                return array_map(static fn (int $id): string => (string) $id, $ids);
            }
        );
        $GLOBALS['wpdb'] = $wpdb;
    }
}
