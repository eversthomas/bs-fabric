<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Entries;

use BsFabric\Entries\EntryAuditRepository;
use BsFabric\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

final class EntryAuditRepositoryTest extends TestCase
{
    public function testLogSkipsInvalidAction(): void
    {
        global $wpdb;

        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldNotReceive('query');

        Functions\when('get_current_user_id')->justReturn(1);

        $repository = new EntryAuditRepository();
        $repository->log(1, 1, 'invalid');

        self::assertTrue(true);
    }

    public function testGetForEntryReturnsEmptyForInvalidId(): void
    {
        $repository = new EntryAuditRepository();

        self::assertSame([], $repository->getForEntry(0));
    }
}
