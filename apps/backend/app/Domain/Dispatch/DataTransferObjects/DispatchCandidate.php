<?php

namespace App\Domain\Dispatch\DataTransferObjects;

use App\Domain\Fleet\Models\TowTruck;

readonly class DispatchCandidate
{
    public function __construct(
        public TowTruck $towTruck,
        public int $distanceMeters,
    ) {}
}
