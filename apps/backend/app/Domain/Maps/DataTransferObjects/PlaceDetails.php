<?php

namespace App\Domain\Maps\DataTransferObjects;

readonly class PlaceDetails
{
    public function __construct(
        public string $placeId,
        public string $formattedAddress,
        public Coordinates $coordinates,
    ) {}
}
