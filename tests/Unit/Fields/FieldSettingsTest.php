<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Fields;

use BsFabric\Fields\FieldSettings;
use BsFabric\Tests\Unit\TestCase;

final class FieldSettingsTest extends TestCase
{
    public function testShowInListDefaultsToFalse(): void
    {
        self::assertFalse(FieldSettings::showInList([]));
        self::assertFalse(FieldSettings::showInList(FieldSettings::globalDefaults()));
    }

    public function testShowInListWhenEnabled(): void
    {
        self::assertTrue(FieldSettings::showInList([
            FieldSettings::SHOW_IN_LIST => true,
        ]));
    }
}
