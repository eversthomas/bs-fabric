<?php

declare(strict_types=1);

namespace BsFabric\Geo;

final class GeocodeResult
{
    public const STATUS_OK = 'ok';

    public const STATUS_FAILED = 'failed';

    public function __construct(
        public readonly string $status,
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
        public readonly string $message = '',
    ) {
    }

    public static function ok(float $lat, float $lng): self
    {
        return new self(self::STATUS_OK, $lat, $lng);
    }

    public static function failed(string $message): self
    {
        return new self(self::STATUS_FAILED, null, null, $message);
    }

    public function isOk(): bool
    {
        return $this->status === self::STATUS_OK
            && $this->lat !== null
            && $this->lng !== null;
    }
}
