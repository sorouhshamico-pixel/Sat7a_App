<?php

namespace App\Domain\Maps\DataTransferObjects;

readonly class PlaceSuggestion
{
    public function __construct(
        public string $placeId,
        public string $description,
    ) {}
}
