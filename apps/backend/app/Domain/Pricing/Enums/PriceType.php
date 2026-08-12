<?php

namespace App\Domain\Pricing\Enums;

enum PriceType: string
{
    case FixedQuote = 'fixed_quote';
    case EstimatedRange = 'estimated_range';
    case ManualQuote = 'manual_quote';
}
