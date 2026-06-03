<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Core;

use BsFabric\Core\PluginSettings;
use BsFabric\Tests\Unit\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

final class PluginSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('sanitize_key')->alias(static fn (string $value): string => strtolower(preg_replace('/[^a-z0-9_\-]/', '', $value) ?? ''));
        Functions\when('sanitize_text_field')->returnArg();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testDefaults(): void
    {
        Functions\when('get_option')->justReturn(false);

        self::assertSame('Fabric', PluginSettings::menuLabel());
        self::assertSame('draft', PluginSettings::defaultEntryStatus());
        self::assertTrue(PluginSettings::lockFieldKeys());
        self::assertFalse(PluginSettings::deleteOnUninstall());
    }

    public function testSaveAndReadCustomValues(): void
    {
        $stored = [];

        Functions\when('get_option')->alias(function (string $key, $default = false) use (&$stored) {
            return $stored[$key] ?? $default;
        });

        Functions\when('update_option')->alias(function (string $key, $value) use (&$stored): bool {
            $stored[$key] = $value;

            return true;
        });

        PluginSettings::save([
            PluginSettings::MENU_LABEL => 'Mein Fabric',
            PluginSettings::DEFAULT_ENTRY_STATUS => 'published',
            PluginSettings::LOCK_FIELD_KEYS => '',
            PluginSettings::DELETE_ON_UNINSTALL => '1',
        ]);

        self::assertSame('Mein Fabric', PluginSettings::menuLabel());
        self::assertSame('published', PluginSettings::defaultEntryStatus());
        self::assertFalse(PluginSettings::lockFieldKeys());
        self::assertTrue(PluginSettings::deleteOnUninstall());
    }

    public function testInvalidStatusFallsBackToDraft(): void
    {
        Functions\when('get_option')->justReturn([
            PluginSettings::DEFAULT_ENTRY_STATUS => 'invalid',
        ]);

        self::assertSame('draft', PluginSettings::defaultEntryStatus());
    }
}
