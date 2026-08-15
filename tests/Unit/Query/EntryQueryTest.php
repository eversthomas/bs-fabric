<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Query;

use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryCollection;
use BsFabric\Entries\EntryQueryExecutor;
use BsFabric\Query\EntryQuery;
use BsFabric\Templates\Template;
use BsFabric\Templates\TemplateLookup;
use BsFabric\Tests\Unit\TestCase;
use Mockery;

final class EntryQueryTest extends TestCase
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
    }

    public function testWhereOrderByAndLimitArePassedToRepository(): void
    {
        $template = new Template([
            'id' => 3,
            'name' => 'article',
            'label' => 'Article',
            'title_field' => null,
            'settings' => [],
            'created_at' => '2026-01-01 00:00:00',
        ]);

        $templateRepository = Mockery::mock(TemplateLookup::class);
        $templateRepository->shouldReceive('getByName')->once()->with('article')->andReturn($template);

        $entryRepository = Mockery::mock(EntryQueryExecutor::class);
        $entryRepository->shouldReceive('findByFrontendQuery')
            ->once()
            ->with(Mockery::on(static function (array $args): bool {
                return $args['template_id'] === 3
                    && $args['wheres'] === [['field' => 'stadt', 'value' => 'Wesel']]
                    && $args['order_by'] === 'name'
                    && $args['order_direction'] === 'ASC'
                    && $args['limit'] === 5
                    && $args['status'] === 'published';
            }))
            ->andReturn(new EntryCollection([$this->createEntry(1)]));

        $query = (new EntryQuery('article', $templateRepository, $entryRepository))
            ->where('stadt', 'Wesel')
            ->status('published')
            ->orderBy('name', 'ASC')
            ->limit(5);

        $collection = $query->get();

        $this->assertCount(1, $collection);
        $this->assertSame(1, $collection->first()?->getId());
    }

    public function testFirstAppliesLimitOneWithoutMutatingOriginalQuery(): void
    {
        $template = new Template([
            'id' => 1,
            'name' => 'article',
            'label' => 'Article',
            'title_field' => null,
            'settings' => [],
            'created_at' => '2026-01-01 00:00:00',
        ]);

        $templateRepository = Mockery::mock(TemplateLookup::class);
        $templateRepository->shouldReceive('getByName')->twice()->andReturn($template);

        $entryRepository = Mockery::mock(EntryQueryExecutor::class);
        $entryRepository->shouldReceive('findByFrontendQuery')
            ->once()
            ->with(Mockery::on(static fn (array $args): bool => $args['limit'] === 1 && $args['status'] === 'published'))
            ->andReturn(new EntryCollection([$this->createEntry(7)]));
        $entryRepository->shouldReceive('findByFrontendQuery')
            ->once()
            ->with(Mockery::on(static fn (array $args): bool => $args['limit'] === null && $args['status'] === 'published'))
            ->andReturn(new EntryCollection([$this->createEntry(7), $this->createEntry(8)]));

        $query = new EntryQuery('article', $templateRepository, $entryRepository);

        $this->assertSame(7, $query->first()?->getId());
        $this->assertCount(2, $query->get());
    }

    public function testCountUsesRepositoryWithoutLimit(): void
    {
        $template = new Template([
            'id' => 2,
            'name' => 'article',
            'label' => 'Article',
            'title_field' => null,
            'settings' => [],
            'created_at' => '2026-01-01 00:00:00',
        ]);

        $templateRepository = Mockery::mock(TemplateLookup::class);
        $templateRepository->shouldReceive('getByName')->once()->andReturn($template);

        $entryRepository = Mockery::mock(EntryQueryExecutor::class);
        $entryRepository->shouldReceive('countByFrontendQuery')
            ->once()
            ->with(Mockery::on(static fn (array $args): bool => $args['limit'] === null && $args['status'] === 'published'))
            ->andReturn(4);

        $count = (new EntryQuery('article', $templateRepository, $entryRepository))
            ->limit(2)
            ->count();

        $this->assertSame(4, $count);
    }

    public function testWithPassesRelationFieldsToRepository(): void
    {
        $template = new Template([
            'id' => 5,
            'name' => 'org',
            'label' => 'Org',
            'title_field' => null,
            'settings' => [],
            'created_at' => '2026-01-01 00:00:00',
        ]);

        $templateRepository = Mockery::mock(TemplateLookup::class);
        $templateRepository->shouldReceive('getByName')->once()->andReturn($template);

        $entryRepository = Mockery::mock(EntryQueryExecutor::class);
        $entryRepository->shouldReceive('findByFrontendQuery')
            ->once()
            ->with(Mockery::on(static fn (array $args): bool => $args['with'] === ['contacts'] && $args['status'] === 'published'))
            ->andReturn(new EntryCollection());

        $collection = (new EntryQuery('org', $templateRepository, $entryRepository))
            ->with('contacts')
            ->get();

        $this->assertCount(0, $collection);
    }

    public function testDefaultStatusIsPublishedWhenNotExplicitlySet(): void
    {
        $template = new Template([
            'id' => 4,
            'name' => 'article',
            'label' => 'Article',
            'title_field' => null,
            'settings' => [],
            'created_at' => '2026-01-01 00:00:00',
        ]);

        $templateRepository = Mockery::mock(TemplateLookup::class);
        $templateRepository->shouldReceive('getByName')->once()->andReturn($template);

        $entryRepository = Mockery::mock(EntryQueryExecutor::class);
        $entryRepository->shouldReceive('findByFrontendQuery')
            ->once()
            ->with(Mockery::on(static fn (array $args): bool => $args['status'] === 'published'))
            ->andReturn(new EntryCollection());

        $collection = (new EntryQuery('article', $templateRepository, $entryRepository))->get();

        $this->assertCount(0, $collection);
    }

    public function testWithAnyStatusPassesNullStatusToRepository(): void
    {
        $template = new Template([
            'id' => 4,
            'name' => 'article',
            'label' => 'Article',
            'title_field' => null,
            'settings' => [],
            'created_at' => '2026-01-01 00:00:00',
        ]);

        $templateRepository = Mockery::mock(TemplateLookup::class);
        $templateRepository->shouldReceive('getByName')->once()->andReturn($template);

        $entryRepository = Mockery::mock(EntryQueryExecutor::class);
        $entryRepository->shouldReceive('findByFrontendQuery')
            ->once()
            ->with(Mockery::on(static fn (array $args): bool => $args['status'] === null))
            ->andReturn(new EntryCollection());

        $collection = (new EntryQuery('article', $templateRepository, $entryRepository))
            ->withAnyStatus()
            ->get();

        $this->assertCount(0, $collection);
    }

    public function testReturnsEmptyCollectionWhenTemplateMissing(): void
    {
        $templateRepository = Mockery::mock(TemplateLookup::class);
        $templateRepository->shouldReceive('getByName')->once()->andReturn(null);

        $entryRepository = Mockery::mock(EntryQueryExecutor::class);
        $entryRepository->shouldNotReceive('findByFrontendQuery');

        $collection = (new EntryQuery('missing', $templateRepository, $entryRepository))->get();

        $this->assertCount(0, $collection);
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
            new \BsFabric\Fields\FieldRegistry(),
            new \BsFabric\Relations\RelationRepository(),
            null,
        );
    }
}
