<?php

namespace App\Domain\Disputes\Enums;

enum DisputeReason: string
{
    case Overcharge = 'overcharge';
    case ServiceQuality = 'service_quality';
    case Damage = 'damage';
    case NoShow = 'no_show';
    case Other = 'other';
}
