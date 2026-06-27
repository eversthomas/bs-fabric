<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Core;

use BsFabric\Core\Capabilities;
use BsFabric\Tests\Unit\TestCase;

final class CapabilitiesTest extends TestCase
{
    public function testMapMetaCapReturnsPrimitiveFabricCaps(): void
    {
        self::assertSame(
            [Capabilities::MANAGE_ENTRIES],
            Capabilities::mapMetaCap([], Capabilities::MANAGE_ENTRIES, 1, [])
        );
    }

    public function testMapMetaCapIgnoresUnknownCaps(): void
    {
        $caps = ['edit_posts'];

        self::assertSame(
            $caps,
            Capabilities::mapMetaCap($caps, 'edit_posts', 1, [])
        );
    }

    public function testGrantRoleCapabilitiesForMitarbeiter(): void
    {
        $user = (object) ['roles' => [Capabilities::ROLE_MITARBEITER]];

        $allcaps = Capabilities::grantRoleCapabilities([], [], [], $user);

        self::assertTrue($allcaps['read']);
        self::assertTrue($allcaps[Capabilities::MANAGE_ENTRIES]);
        self::assertArrayNotHasKey(Capabilities::MANAGE_SCHEMA, $allcaps);
        self::assertArrayNotHasKey(Capabilities::ASSIGN_TEMPLATE_FIELDS, $allcaps);
    }
}
