<?php

namespace App\Domain\Pricing\Enums;

/**
 * A pricing-relevant classification, deliberately separate from the
 * customer-facing free-text `vehicles.type` field (see
 * docs/DATABASE_SCHEMA.md §Phase 5) — that field is descriptive display
 * text; this is a small, fixed set used only to look up a fee multiplier.
 */
enum VehicleCategory: string
{
    case Sedan = 'sedan';
    case Suv = 'suv';
    case Van = 'van';
    case Luxury = 'luxury';
    case Heavy = 'heavy';
    case Motorcycle = 'motorcycle';
}
