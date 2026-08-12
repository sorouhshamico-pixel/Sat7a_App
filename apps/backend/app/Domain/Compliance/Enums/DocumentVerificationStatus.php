<?php

namespace App\Domain\Compliance\Enums;

enum DocumentVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
