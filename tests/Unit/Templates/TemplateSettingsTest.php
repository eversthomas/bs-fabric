<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Templates;

use BsFabric\Templates\TemplateSettings;
use BsFabric\Tests\Unit\TestCase;

final class TemplateSettingsTest extends TestCase
{
    public function testShowInMenuDefaultsToTrue(): void
    {
        self::assertTrue(TemplateSettings::showInMenu([]));
        self::assertTrue(TemplateSettings::showInMenu(TemplateSettings::globalDefaults()));
    }

    public function testShowInMenuWhenDisabled(): void
    {
        self::assertFalse(TemplateSettings::showInMenu([
            TemplateSettings::SHOW_IN_MENU => false,
        ]));
    }
}
