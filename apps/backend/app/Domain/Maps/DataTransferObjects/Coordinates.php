<?php

namespace App\Domain\Maps\DataTransferObjects;

readonly class Coordinates
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    /**
     * @return array{latitude: float, longitude: float}
     */
    public function toArray(): array
    {
        return ['latitude' => $this->latitude, 'longitude' => $this->longitude];
    }
}
