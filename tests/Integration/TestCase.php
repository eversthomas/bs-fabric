<?php

declare(strict_types=1);

namespace BsFabric\Tests\Integration;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    private static bool $bootstrapped = false;

    public static function setUpBeforeClass(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        $pluginDir = dirname(__DIR__, 2);
        $wpLoad = $pluginDir . '/../../../wp-load.php';

        if (! is_readable($wpLoad)) {
            self::markTestSkipped('WordPress installation not found for integration tests.');
        }

        require_once $wpLoad;

        \BsFabric\Core\Capabilities::register();
        \BsFabric\Core\Plugin::instance()->activate();

        self::$bootstrapped = true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->truncateFieldsTables();
    }

    protected function truncateFieldsTables(): void
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
