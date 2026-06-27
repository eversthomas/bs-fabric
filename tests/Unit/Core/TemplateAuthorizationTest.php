<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Core;

use BsFabric\Core\Capabilities;
use BsFabric\Core\TemplateAuthorization;
use BsFabric\Templates\Template;
use BsFabric\Templates\TemplateSettings;
use BsFabric\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

final class TemplateAuthorizationTest extends TestCase
{
    public function testAdminCanManageTemplateFieldsWithoutSetting(): void
    {
        Functions\when('current_user_can')->alias(function (string $cap): bool {
            return $cap === Capabilities::MANAGE_SCHEMA;
        });

        $template = new Template([
            'id' => 1,
            'name' => 'standort',
            'label' => 'Standort',
            'title_field' => null,
            'settings' => TemplateSettings::globalDefaults(),
            'created_at' => '2026-01-01 00:00:00',
        ]);

        self::assertTrue(TemplateAuthorization::canManageTemplateFields($template));
    }

    public function testEditorNeedsAssignCapabilityAndTemplateSetting(): void
    {
        $unlockedSettings = [
            TemplateSettings::EDITORS_MAY_MANAGE_FIELDS => true,
        ];

        $template = new Template([
            'id' => 2,
            'name' => 'standort',
            'label' => 'Standort',
            'title_field' => null,
            'settings' => $unlockedSettings,
            'created_at' => '2026-01-01 00:00:00',
        ]);

        Functions\when('current_user_can')->alias(function (string $cap): bool {
            return $cap === Capabilities::MANAGE_ENTRIES;
        });

        self::assertFalse(TemplateAuthorization::canManageTemplateFields($template));

        Functions\when('current_user_can')->alias(function (string $cap): bool {
            return in_array($cap, [Capabilities::MANAGE_ENTRIES, Capabilities::ASSIGN_TEMPLATE_FIELDS], true);
        });

        self::assertTrue(TemplateAuthorization::canManageTemplateFields($template));
    }

    public function testEditorWithoutTemplateSettingCannotAssignFields(): void
    {
        Functions\when('current_user_can')->alias(function (string $cap): bool {
            return in_array($cap, [Capabilities::MANAGE_ENTRIES, Capabilities::ASSIGN_TEMPLATE_FIELDS], true);
        });

        $template = new Template([
            'id' => 1,
            'name' => 'standort',
            'label' => 'Standort',
            'title_field' => null,
            'settings' => TemplateSettings::globalDefaults(),
            'created_at' => '2026-01-01 00:00:00',
        ]);

        self::assertFalse(TemplateAuthorization::canManageTemplateFields($template));
    }

    public function testMitarbeiterCannotAssignFieldsEvenWhenTemplateAllows(): void
    {
        Functions\when('current_user_can')->alias(function (string $cap): bool {
            return $cap === Capabilities::MANAGE_ENTRIES;
        });

        $template = new Template([
            'id' => 1,
            'name' => 'standort',
            'label' => 'Standort',
            'title_field' => null,
            'settings' => [
                TemplateSettings::EDITORS_MAY_MANAGE_FIELDS => true,
            ],
            'created_at' => '2026-01-01 00:00:00',
        ]);

        self::assertFalse(TemplateAuthorization::canManageTemplateFields($template));
        self::assertTrue(TemplateAuthorization::canManageEntries());
    }
}
