<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Admin\Ajax;

use BsFabric\Admin\Ajax\EntryAjax;
use BsFabric\Entries\EntryAdminOperations;
use BsFabric\Entries\EntryNotFoundException;
use BsFabric\Templates\TemplateLookup;
use Brain\Monkey\Functions;
use Mockery;

final class EntryAjaxTest extends AjaxTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAjaxStubs();
    }

    public function testFetchExplorerRowsRequiresCapability(): void
    {
        Functions\when('current_user_can')->justReturn(false);

        $_POST = ['nonce' => 'valid'];

        $ajax = new EntryAjax(
            Mockery::mock(EntryAdminOperations::class),
            Mockery::mock(TemplateLookup::class),
        );

        $payload = $this->expectJsonError(static fn (): mixed => $ajax->fetchExplorerRows(), 403);

        self::assertSame('Keine Berechtigung.', $payload['message']);
    }

    public function testFetchExplorerRowsReturnsFormattedEntries(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('check_ajax_referer')->once()->with(EntryAjax::NONCE_ACTION, 'nonce');
        Functions\when('admin_url')->justReturn('http://example.test/wp-admin/admin.php');
        Functions\when('add_query_arg')->justReturn('http://example.test/wp-admin/admin.php?page=edit');
        Functions\when('get_option')->justReturn('Y-m-d');
        Functions\when('wp_date')->justReturn('2026-01-01 12:00');

        $_POST = [
            'nonce' => 'valid',
            'page' => '1',
            'per_page' => '20',
            'search' => 'test',
            'sort_field' => 'created_at',
            'sort_direction' => 'desc',
            'filters' => json_encode([
                ['field' => 'template_id', 'operator' => 'is', 'value' => 3],
                ['field' => 'status', 'operator' => 'is', 'value' => 'published'],
            ]),
        ];

        $entryRepository = Mockery::mock(EntryAdminOperations::class);
        $entryRepository->shouldReceive('queryExplorerRows')
            ->once()
            ->with([
                'page' => 1,
                'per_page' => 20,
                'search' => 'test',
                'template_id' => 3,
                'status' => 'published',
                'sort_field' => 'created_at',
                'sort_direction' => 'desc',
            ])
            ->andReturn([
                'rows' => [
                    [
                        'id' => 7,
                        'title' => 'Beispiel',
                        'template_id' => 3,
                        'template_label' => 'Artikel',
                        'status' => 'published',
                        'created_at' => '2026-01-01 10:00:00',
                    ],
                ],
                'total' => 1,
                'total_pages' => 1,
            ]);

        $ajax = new EntryAjax(
            $entryRepository,
            Mockery::mock(TemplateLookup::class),
        );

        $payload = $this->expectJsonSuccess(static fn (): mixed => $ajax->fetchExplorerRows());

        self::assertCount(1, $payload['entries']);
        self::assertSame(7, $payload['entries'][0]['id']);
        self::assertSame(1, $payload['pagination']['totalItems']);
    }

    public function testDeleteEntryRejectsInvalidId(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('check_ajax_referer')->once()->with(EntryAjax::NONCE_ACTION, 'nonce');

        $_POST = ['nonce' => 'valid', 'entry_id' => '0'];

        $ajax = new EntryAjax(
            Mockery::mock(EntryAdminOperations::class),
            Mockery::mock(TemplateLookup::class),
        );

        $payload = $this->expectJsonError(static fn (): mixed => $ajax->deleteEntry(), 400);

        self::assertSame('Ungültige Entry-ID.', $payload['message']);
    }

    public function testDeleteEntryReturnsNotFound(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('check_ajax_referer')->once()->with(EntryAjax::NONCE_ACTION, 'nonce');

        $_POST = ['nonce' => 'valid', 'entry_id' => '42'];

        $entryRepository = Mockery::mock(EntryAdminOperations::class);
        $entryRepository->shouldReceive('delete')->once()->with(42)->andThrow(EntryNotFoundException::class);

        $ajax = new EntryAjax(
            $entryRepository,
            Mockery::mock(TemplateLookup::class),
        );

        $payload = $this->expectJsonError(static fn (): mixed => $ajax->deleteEntry(), 404);

        self::assertSame('Entry nicht gefunden.', $payload['message']);
    }

    public function testSearchRelationEntriesReturnsEmptyWhenTemplateMissing(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('check_ajax_referer')->once()->with(EntryAjax::NONCE_ACTION, 'nonce');

        $_POST = [
            'nonce' => 'valid',
            'template' => 'article',
            'search' => '',
            'exclude_entry_id' => '0',
        ];

        $templateRepository = Mockery::mock(TemplateLookup::class);
        $templateRepository->shouldReceive('getByName')->once()->with('article')->andReturn(null);

        $ajax = new EntryAjax(
            Mockery::mock(EntryAdminOperations::class),
            $templateRepository,
        );

        $payload = $this->expectJsonSuccess(static fn (): mixed => $ajax->searchRelationEntries());

        self::assertSame([], $payload['results']);
    }

    public function testBulkEntriesPublishesSelected(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('check_ajax_referer')->once()->with(EntryAjax::NONCE_ACTION, 'nonce');

        $_POST = [
            'nonce' => 'valid',
            'template_id' => '3',
            'bulk_action' => 'publish',
            'entry_ids' => ['10', '11'],
        ];

        $entryRepository = Mockery::mock(EntryAdminOperations::class);
        $entryRepository->shouldReceive('getTemplateIdForEntry')->with(10)->andReturn(3);
        $entryRepository->shouldReceive('getTemplateIdForEntry')->with(11)->andReturn(3);
        $entryRepository->shouldReceive('updateStatus')->once()->with(10, 'published')->andReturn(true);
        $entryRepository->shouldReceive('updateStatus')->once()->with(11, 'published')->andReturn(true);

        $ajax = new EntryAjax(
            $entryRepository,
            Mockery::mock(TemplateLookup::class),
        );

        $payload = $this->expectJsonSuccess(static fn (): mixed => $ajax->bulkEntries());

        self::assertSame(2, $payload['processed']);
    }
}
