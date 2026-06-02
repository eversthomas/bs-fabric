<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Database;

use BsFabric\Database\Installer;
use BsFabric\Database\Migrator;
use BsFabric\Tests\Unit\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

final class MigratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testNeedsMigrationWhenOptionMissing(): void
    {
        Functions\when('get_option')->justReturn('0');

        $migrator = new Migrator();

        self::assertTrue($migrator->needsMigration());
    }

    public function testDoesNotNeedMigrationWhenVersionIsCurrent(): void
    {
        Functions\when('get_option')->justReturn(Installer::DB_VERSION);

        $migrator = new Migrator();

        self::assertFalse($migrator->needsMigration());
    }

    public function testNeedsMigrationWhenVersionIsOlder(): void
    {
        Functions\when('get_option')->justReturn('0.9.0');

        $migrator = new Migrator();

        self::assertTrue($migrator->needsMigration());
    }
}
