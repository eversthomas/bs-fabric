<?php

declare(strict_types=1);

namespace BsFabric\Geo;

interface GeocoderInterface
{
    /**
     * @param array{
     *     street?: string,
     *     zip?: string,
     *     city?: string,
     *     country?: string,
     *     formatted?: string
     * } $address
     */
    public function geocode(array $address): GeocodeResult;
}
