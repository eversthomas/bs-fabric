<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Geo;

use BsFabric\Geo\GeocodeResult;
use BsFabric\Tests\Unit\TestCase;

final class GeocodeResultTest extends TestCase
{
    public function testOkResult(): void
    {
        $result = GeocodeResult::ok(52.52, 13.405);

        $this->assertTrue($result->isOk());
        $this->assertSame(GeocodeResult::STATUS_OK, $result->status);
        $this->assertSame(52.52, $result->lat);
        $this->assertSame(13.405, $result->lng);
    }

    public function testFailedResult(): void
    {
        $result = GeocodeResult::failed('Nope');

        $this->assertFalse($result->isOk());
        $this->assertSame(GeocodeResult::STATUS_FAILED, $result->status);
        $this->assertNull($result->lat);
        $this->assertSame('Nope', $result->message);
    }
}
