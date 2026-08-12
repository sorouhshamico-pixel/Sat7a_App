<?php

namespace App\Domain\Maps\DataTransferObjects;

readonly class RouteInfo
{
    public function __construct(
        public int $distanceMeters,
        public int $durationSeconds,
    ) {}
}
