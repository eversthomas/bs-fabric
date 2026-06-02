<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Api;

use BsFabric\Api\EntryQueryFactory;
use BsFabric\Api\EntryResolver;
use BsFabric\Api\PublicApi;
use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryQueryExecutor;
use BsFabric\Query\EntryQuery;
use BsFabric\Templates\Template;
use BsFabric\Templates\TemplateLookup;
use BsFabric\Tests\Unit\TestCase;
use Mockery;

final class PublicApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Brain\Monkey\Functions\when('sanitize_key')->alias(
            static fn (string $value): string => strtolower(preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '')
        );
        \Brain\Monkey\Functions\when('absint')->alias(
            static fn (mixed $value): int => abs((int) $value)
        );
    }

    public function testResolveReturnsEntryQueryForTemplateName(): void
    {
        $api = $this->createApi();
        $query = PublicApi::resolve('article');

        $this->assertInstanceOf(EntryQuery::class, $query);
        $this->assertSame('article', $query->getTemplateName());
    }

    public function testResolveReturnsEntryForHashSyntax(): void
    {
        $entry = $this->createEntry(42);
        $templateRepository = Mockery::mock(TemplateLookup::class);
        $templateRepository->shouldReceive('getByName')->once()->with('article')->andReturn($this->createTemplate());

        $entryRepository = Mockery::mock(EntryQueryExecutor::class);
        $entryRepository->shouldReceive('getById')->once()->with(42)->andReturn($entry);

        $api = new PublicApi(
            new EntryQueryFactory($templateRepository, $entryRepository),
            new EntryResolver($templateRepository, $entryRepository),
        );

        $this->registerApi($api);

        $resolved = PublicApi::resolve('article#42');

        $this->assertInstanceOf(Entry::class, $resolved);
        $this->assertSame(42, $resolved->getId());
    }

    public function testResolveReturnsEntryForSecondArgumentSyntax(): void
    {
        $entry = $this->createEntry(99, 2);
        $templateRepository = Mockery::mock(TemplateLookup::class);
        $templateRepository->shouldReceive('getByName')->once()->with('event')->andReturn($this->createTemplate(2, 'event'));

        $entryRepository = Mockery::mock(EntryQueryExecutor::class);
        $entryRepository->shouldReceive('getById')->once()->with(99)->andReturn($entry);

        $api = new PublicApi(
            new EntryQueryFactory($templateRepository, $entryRepository),
            new EntryResolver($templateRepository, $entryRepository),
        );

        $this->registerApi($api);

        $resolved = PublicApi::resolve('event', 99);

        $this->assertInstanceOf(Entry::class, $resolved);
        $this->assertSame(99, $resolved->getId());
    }

    public function testResolveReturnsNullForInvalidSelector(): void
    {
        $this->createApi();

        $this->assertNull(PublicApi::resolve(''));
        $this->assertNull(PublicApi::resolve('article#0'));
    }

    private function createApi(): PublicApi
    {
        $templateRepository = Mockery::mock(TemplateLookup::class);
        $templateRepository->shouldReceive('getByName')->andReturn($this->createTemplate());

        $entryRepository = Mockery::mock(EntryQueryExecutor::class);

        $api = new PublicApi(
            new EntryQueryFactory($templateRepository, $entryRepository),
            new EntryResolver($templateRepository, $entryRepository),
        );

        $this->registerApi($api);

        return $api;
    }

    private function registerApi(PublicApi $api): void
    {
        $plugin = \BsFabric\Core\Plugin::instance();
        $plugin->container()->set(PublicApi::SERVICE_ID, static fn (): PublicApi => $api);
    }

    private function createTemplate(int $id = 1, string $name = 'article'): Template
    {
        return new Template([
            'id' => $id,
            'name' => $name,
            'label' => ucfirst($name),
            'title_field' => null,
            'settings' => [],
            'created_at' => '2026-01-01 00:00:00',
        ]);
    }

    private function createEntry(int $id, int $templateId = 1): Entry
    {
        return new Entry(
            [
                'id' => $id,
                'template_id' => $templateId,
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
