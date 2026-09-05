<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Frontend;

use BsFabric\Api\EntryResolver;
use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryQueryExecutor;
use BsFabric\Fields\FieldRegistry;
use BsFabric\Frontend\EntryEmbedRenderer;
use BsFabric\Relations\RelationRepository;
use BsFabric\Templates\Template;
use BsFabric\Templates\TemplateLookup;
use BsFabric\Tests\Unit\TestCase;
use Mockery;

final class EntryEmbedRendererTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Brain\Monkey\Functions\when('sanitize_key')->alias(
            static fn (string $value): string => strtolower(preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '')
        );
    }

    public function testEmptyTemplateNameReturnsEmptyString(): void
    {
        self::assertSame('', $this->createRenderer()->render('', [1, 2], 'card'));
    }

    public function testEmptyEntryIdsReturnsEmptyString(): void
    {
        self::assertSame('', $this->createRenderer()->render('referenz', [], 'card'));
    }

    public function testOnlyUnpublishedEntriesReturnsEmptyString(): void
    {
        $template = new Template([
            'id' => 5,
            'name' => 'referenz',
            'label' => 'Referenz',
            'title_field' => null,
            'settings' => [],
            'created_at' => '2026-01-01 00:00:00',
        ]);

        $draftEntry = new Entry(
            [
                'id' => 42,
                'template_id' => 5,
                'title' => 'Entwurf',
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

        $templateLookup = Mockery::mock(TemplateLookup::class);
        $templateLookup->shouldReceive('getByName')->with('referenz')->andReturn($template);

        $entryQueryExecutor = Mockery::mock(EntryQueryExecutor::class);
        $entryQueryExecutor->shouldReceive('getById')->with(42)->andReturn($draftEntry);

        $renderer = new EntryEmbedRenderer(new EntryResolver($templateLookup, $entryQueryExecutor));

        // Nur ein Entwurf ausgewählt -> darf auf der öffentlichen Seite nicht auftauchen.
        self::assertSame('', $renderer->render('referenz', [42], 'card'));
    }

    private function createRenderer(): EntryEmbedRenderer
    {
        $templateLookup = Mockery::mock(TemplateLookup::class);
        $entryQueryExecutor = Mockery::mock(EntryQueryExecutor::class);

        return new EntryEmbedRenderer(new EntryResolver($templateLookup, $entryQueryExecutor));
    }
}
