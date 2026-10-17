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

    public function testShowInWpMenuDefaultsToFalse(): void
    {
        self::assertFalse(TemplateSettings::showInWpMenu([]));
        self::assertFalse(TemplateSettings::showInWpMenu(TemplateSettings::globalDefaults()));
    }

    public function testShowInWpMenuRequiresFabricMenu(): void
    {
        self::assertFalse(TemplateSettings::showInWpMenu([
            TemplateSettings::SHOW_IN_MENU => false,
            TemplateSettings::SHOW_IN_WP_MENU => true,
        ]));
    }

    public function testShowInWpMenuWhenEnabled(): void
    {
        self::assertTrue(TemplateSettings::showInWpMenu([
            TemplateSettings::SHOW_IN_MENU => true,
            TemplateSettings::SHOW_IN_WP_MENU => true,
        ]));
    }

    public function testEditorsMayManageFieldsDefaultsToFalse(): void
    {
        self::assertFalse(TemplateSettings::editorsMayManageFields([]));
        self::assertFalse(TemplateSettings::editorsMayManageFields(TemplateSettings::globalDefaults()));
    }

    public function testEditorsMayManageFieldsWhenEnabled(): void
    {
        self::assertTrue(TemplateSettings::editorsMayManageFields([
            TemplateSettings::EDITORS_MAY_MANAGE_FIELDS => true,
        ]));
    }

    public function testRestVisibleDefaultsToTrue(): void
    {
        self::assertTrue(TemplateSettings::restVisible([]));
        self::assertTrue(TemplateSettings::restVisible(TemplateSettings::globalDefaults()));
    }

    public function testRestVisibleWhenDisabled(): void
    {
        self::assertFalse(TemplateSettings::restVisible([
            TemplateSettings::REST_VISIBLE => false,
        ]));
    }

    public function testPublicDetailDefaultsToFalse(): void
    {
        self::assertFalse(TemplateSettings::publicDetail([]));
        self::assertFalse(TemplateSettings::publicDetail(TemplateSettings::globalDefaults()));
    }

    public function testPublicDetailWhenEnabled(): void
    {
        self::assertTrue(TemplateSettings::publicDetail([
            TemplateSettings::PUBLIC_DETAIL => true,
        ]));
    }
}
