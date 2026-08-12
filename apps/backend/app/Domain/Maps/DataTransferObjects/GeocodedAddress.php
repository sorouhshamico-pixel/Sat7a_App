<?php

namespace App\Domain\Maps\DataTransferObjects;

readonly class GeocodedAddress
{
    public function __construct(
        public Coordinates $coordinates,
        public string $formattedAddress,
        public ?string $placeId = null,
    ) {}
}
