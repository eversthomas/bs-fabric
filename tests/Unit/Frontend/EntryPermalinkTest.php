<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Frontend;

use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryQueryExecutor;
use BsFabric\Fields\FieldRegistry;
use BsFabric\Frontend\EntryPermalink;
use BsFabric\Relations\RelationRepository;
use BsFabric\Templates\Template;
use BsFabric\Templates\TemplateLookup;
use BsFabric\Templates\TemplateSettings;
use BsFabric\Tests\Unit\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

final class EntryPermalinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('sanitize_key')->alias(
            static fn (string $value): string => strtolower(preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '')
        );
        Functions\when('sanitize_title')->alias(
            static fn (string $value): string => strtolower(preg_replace('/[^a-z0-9\-]+/', '-', $value) ?? '')
        );
        Functions\when('home_url')->alias(
            static fn (string $path = ''): string => 'https://example.test' . $path
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testUrlForReturnsNullWhenTemplateHasNoPublicDetail(): void
    {
        $templates = Mockery::mock(TemplateLookup::class);
        $templates->shouldReceive('getById')->once()->with(1)->andReturn(
            new Template([
                'id' => 1,
                'name' => 'einrichtung',
                'label' => 'Einrichtung',
                'title_field' => null,
                'settings' => [TemplateSettings::PUBLIC_DETAIL => false],
                'created_at' => '2026-01-01 00:00:00',
            ])
        );

        $permalink = new EntryPermalink(
            $templates,
            Mockery::mock(EntryQueryExecutor::class)
        );

        self::assertNull($permalink->urlFor($this->createEntry()));
    }

    public function testUrlForReturnsPrettyUrlWhenPublishedAndEnabled(): void
    {
        $templates = Mockery::mock(TemplateLookup::class);
        $templates->shouldReceive('getById')->once()->with(1)->andReturn(
            new Template([
                'id' => 1,
                'name' => 'einrichtung',
                'label' => 'Einrichtung',
                'title_field' => null,
                'settings' => [TemplateSettings::PUBLIC_DETAIL => true],
                'created_at' => '2026-01-01 00:00:00',
            ])
        );

        $permalink = new EntryPermalink(
            $templates,
            Mockery::mock(EntryQueryExecutor::class)
        );

        self::assertSame(
            'https://example.test/einrichtung/haus-sonne/',
            $permalink->urlFor($this->createEntry())
        );
    }

    public function testUrlForReturnsNullForDraft(): void
    {
        $permalink = new EntryPermalink(
            Mockery::mock(TemplateLookup::class),
            Mockery::mock(EntryQueryExecutor::class)
        );

        self::assertNull($permalink->urlFor($this->createEntry('draft')));
    }

    public function testBuildUrl(): void
    {
        $permalink = new EntryPermalink(
            Mockery::mock(TemplateLookup::class),
            Mockery::mock(EntryQueryExecutor::class)
        );

        self::assertSame(
            'https://example.test/einrichtung/haus-sonne/',
            $permalink->buildUrl('einrichtung', 'haus-sonne')
        );
    }

    private function createEntry(string $status = 'published'): Entry
    {
        return new Entry(
            [
                'id' => 42,
                'template_id' => 1,
                'title' => 'Haus Sonne',
                'slug' => 'haus-sonne',
                'status' => $status,
                'language' => 'de',
                'created_by' => null,
                'updated_by' => null,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ],
            [],
            [],
            new FieldRegistry(),
            new RelationRepository(),
            null
        );
    }
}
