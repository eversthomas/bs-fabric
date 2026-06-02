<?php

declare(strict_types=1);

namespace BsFabric\Tests\Integration;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    private static bool $bootstrapped = false;

    public static function setUpBeforeClass(): void
    {
        if (getenv('BS_FABRIC_INTEGRATION_TESTS') !== '1') {
            self::markTestSkipped(
                'Integration tests are disabled by default because they DELETE all bs_fabric table data. '
                . 'Set BS_FABRIC_INTEGRATION_TESTS=1 (and ideally BS_FABRIC_TEST_DB_NAME) to run them.'
            );
        }

        if (self::$bootstrapped) {
            return;
        }

        $pluginDir = dirname(__DIR__, 2);
        $wpLoad = $pluginDir . '/../../../wp-load.php';

        if (! is_readable($wpLoad)) {
            self::markTestSkipped('WordPress installation not found for integration tests.');
        }

        require_once $wpLoad;

        self::assertSafeTestDatabase();

        \BsFabric\Core\Capabilities::register();
        \BsFabric\Core\Plugin::instance()->activate();

        self::$bootstrapped = true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::assertSafeTestDatabase();
        $this->truncateFabricTables();
    }

    /**
     * Integration tests must never run against a developer's main WordPress database.
     */
    private static function assertSafeTestDatabase(): void
    {
        global $wpdb;

        $expectedDbName = getenv('BS_FABRIC_TEST_DB_NAME');

        if (! is_string($expectedDbName) || $expectedDbName === '') {
            self::markTestSkipped(
                'Set BS_FABRIC_TEST_DB_NAME to the database name of your dedicated WordPress test install '
                . 'before running integration tests.'
            );
        }

        if ($wpdb->dbname !== $expectedDbName) {
            self::markTestSkipped(
                sprintf(
                    'Refusing to wipe database "%s". BS_FABRIC_TEST_DB_NAME is set to "%s".',
                    (string) $wpdb->dbname,
                    $expectedDbName
                )
            );
        }
    }

    protected function truncateFabricTables(): void
    {
        global $wpdb;

        $templatesTable = $wpdb->prefix . 'bs_fabric_templates';
        $templateFieldsTable = $wpdb->prefix . 'bs_fabric_template_fields';

        $wpdb->query('DELETE FROM ' . $wpdb->prefix . 'bs_fabric_entry_values');
        $wpdb->query('DELETE FROM ' . $wpdb->prefix . 'bs_fabric_relations');
        $wpdb->query('DELETE FROM ' . $wpdb->prefix . 'bs_fabric_entries');
        $wpdb->query('DELETE FROM ' . $templateFieldsTable);
        $wpdb->query('DELETE FROM ' . $templatesTable);
        $wpdb->query('DELETE FROM ' . $wpdb->prefix . 'bs_fabric_fields');
    }
}
