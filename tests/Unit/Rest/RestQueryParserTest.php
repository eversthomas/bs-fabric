<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Rest;

use BsFabric\Api\EntryQueryFactory;
use BsFabric\Core\Capabilities;
use BsFabric\Entries\EntryCollection;
use BsFabric\Entries\EntryQueryExecutor;
use BsFabric\Rest\RestQueryParser;
use BsFabric\Templates\Template;
use BsFabric\Templates\TemplateLookup;
use BsFabric\Tests\Unit\TestCase;
use Mockery;
use WP_REST_Request;

final class RestQueryParserTest extends TestCase
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
        \Brain\Monkey\Functions\when('absint')->alias(
            static fn (mixed $value): int => abs((int) $value)
        );
        \Brain\Monkey\Functions\when('current_user_can')->justReturn(false);
    }

    public function testMapsWhereOrderLimitAndPublishedStatus(): void
    {
        $template = $this->createTemplate();
        $executor = $this->mockExecutorExpectingArgs([
            'template_id' => 3,
            'wheres' => [
                ['field' => 'stadt', 'value' => 'Wesel'],
                ['field' => 'aktiv', 'value' => '1'],
            ],
            'with' => [],
            'status' => 'published',
            'order_by' => 'name',
            'order_direction' => 'ASC',
            'limit' => 25,
        ]);

        $parser = $this->createParser($template, $executor);
        $request = new WP_REST_Request('GET', '/bs-fabric/v1/templates/einrichtung/entries');
        $request->set_param('template', 'einrichtung');
        $request->set_param('where', [
            'stadt' => 'Wesel',
            'aktiv' => true,
        ]);
        $request->set_param('orderby', 'name');
        $request->set_param('order', 'asc');
        $request->set_param('per_page', 25);

        $query = $parser->fromRequest($request)->getQuery();
        $collection = $query->get();

        $this->assertCount(0, $collection);
    }

    public function testCapsPerPageAtOneHundred(): void
    {
        $template = $this->createTemplate();
        $executor = $this->mockExecutorExpectingArgs([
            'template_id' => 3,
            'wheres' => [],
            'with' => [],
            'status' => 'published',
            'order_by' => 'created_at',
            'order_direction' => 'DESC',
            'limit' => 100,
        ]);

        $parser = $this->createParser($template, $executor);
        $request = new WP_REST_Request('GET', '/bs-fabric/v1/templates/einrichtung/entries');
        $request->set_param('template', 'einrichtung');
        $request->set_param('per_page', 500);

        $parser->fromRequest($request)->get();
    }

    public function testUnauthenticatedDraftStatusFallsBackToPublished(): void
    {
        $template = $this->createTemplate();
        $executor = $this->mockExecutorExpectingArgs([
            'template_id' => 3,
            'wheres' => [],
            'with' => [],
            'status' => 'published',
            'order_by' => 'created_at',
            'order_direction' => 'DESC',
            'limit' => 10,
        ]);

        $parser = $this->createParser($template, $executor);
        $request = new WP_REST_Request('GET', '/bs-fabric/v1/templates/einrichtung/entries');
        $request->set_param('template', 'einrichtung');
        $request->set_param('status', 'draft');

        $parser->fromRequest($request)->getQuery()->get();
    }

    public function testAuthenticatedAnyStatusUsesWithAnyStatus(): void
    {
        \Brain\Monkey\Functions\when('current_user_can')
            ->alias(static fn (string $cap): bool => $cap === Capabilities::MANAGE_ENTRIES);

        $template = $this->createTemplate();
        $executor = $this->mockExecutorExpectingArgs([
            'template_id' => 3,
            'wheres' => [],
            'with' => [],
            'status' => null,
            'order_by' => 'created_at',
            'order_direction' => 'DESC',
            'limit' => 10,
        ]);

        $parser = $this->createParser($template, $executor);
        $request = new WP_REST_Request('GET', '/bs-fabric/v1/templates/einrichtung/entries');
        $request->set_param('template', 'einrichtung');
        $request->set_param('status', 'any');

        $parser->fromRequest($request)->getQuery()->get();
    }

    public function testWithParameterAppliesRelationEmbed(): void
    {
        $template = $this->createTemplate();
        $executor = $this->mockExecutorExpectingArgs([
            'template_id' => 3,
            'wheres' => [],
            'with' => ['ansprechpartner', 'dokumente'],
            'status' => 'published',
            'order_by' => 'created_at',
            'order_direction' => 'DESC',
            'limit' => 10,
        ]);

        $parser = $this->createParser($template, $executor);
        $request = new WP_REST_Request('GET', '/bs-fabric/v1/templates/einrichtung/entries');
        $request->set_param('template', 'einrichtung');
        $request->set_param('with', 'ansprechpartner,dokumente');

        $result = $parser->fromRequest($request);

        $this->assertSame(['ansprechpartner', 'dokumente'], $result->getEmbedRelations());
        $result->getQuery()->get();
    }

    public function testParseEmbedRelationsReturnsUniqueSanitizedFieldNames(): void
    {
        $parser = $this->createParser($this->createTemplate(), Mockery::mock(EntryQueryExecutor::class));
        $request = new WP_REST_Request('GET', '/bs-fabric/v1/templates/einrichtung/entries');
        $request->set_param('with', ' Ansprechpartner , ansprechpartner , dokumente ');

        $this->assertSame(['ansprechpartner', 'dokumente'], $parser->parseEmbedRelations($request));
    }

    private function createTemplate(): Template
    {
        return new Template([
            'id' => 3,
            'name' => 'einrichtung',
            'label' => 'Einrichtung',
            'title_field' => null,
            'settings' => [],
            'created_at' => '2026-01-01 00:00:00',
        ]);
    }

    /**
     * @param array{
     *     template_id: int,
     *     wheres: list<array{field: string, value: mixed}>,
     *     with: list<string>,
     *     status: string|null,
     *     order_by: string|null,
     *     order_direction: string,
     *     limit: int|null
     * } $expectedArgs
     */
    private function mockExecutorExpectingArgs(array $expectedArgs): EntryQueryExecutor
    {
        $executor = Mockery::mock(EntryQueryExecutor::class);
        $executor->shouldReceive('findByFrontendQuery')
            ->once()
            ->with(Mockery::on(static function (array $args) use ($expectedArgs): bool {
                return $args === $expectedArgs;
            }))
            ->andReturn(new EntryCollection());

        return $executor;
    }

    private function createParser(Template $template, EntryQueryExecutor $executor): RestQueryParser
    {
        $templateRepository = Mockery::mock(TemplateLookup::class);
        $templateRepository->shouldReceive('getByName')->andReturn($template);

        return new RestQueryParser(new EntryQueryFactory($templateRepository, $executor));
    }
}
