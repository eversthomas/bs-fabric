<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Geo;

use BsFabric\Geo\AddressGeocodingService;
use BsFabric\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

final class AddressGeocodingServiceTest extends TestCase
{
    public function testFingerprintIsStableForSameAddress(): void
    {
        $a = AddressGeocodingService::fingerprint([
            'street' => 'Musterstraße 1',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'de',
        ]);
        $b = AddressGeocodingService::fingerprint([
            'street' => 'Musterstraße 1',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
        ]);

        $this->assertSame($a, $b);
        $this->assertSame(64, strlen($a));
    }

    public function testFingerprintChangesWhenAddressChanges(): void
    {
        $a = AddressGeocodingService::fingerprint([
            'street' => 'Musterstraße 1',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
        ]);
        $b = AddressGeocodingService::fingerprint([
            'street' => 'Musterstraße 2',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
        ]);

        $this->assertNotSame($a, $b);
    }

    public function testConsumeAdminNoticeReadsAndDeletesTransient(): void
    {
        $stored = [];

        Functions\when('get_current_user_id')->justReturn(7);
        Functions\when('get_transient')->alias(static function (string $key) use (&$stored) {
            return $stored[$key] ?? false;
        });
        Functions\when('delete_transient')->alias(static function (string $key) use (&$stored): bool {
            unset($stored[$key]);

            return true;
        });

        $key = AddressGeocodingService::NOTICE_TRANSIENT . '_7';
        $stored[$key] = 'Standort: Keine Treffer';

        $this->assertSame('Standort: Keine Treffer', AddressGeocodingService::consumeAdminNotice());
        $this->assertNull(AddressGeocodingService::consumeAdminNotice());
    }
}
