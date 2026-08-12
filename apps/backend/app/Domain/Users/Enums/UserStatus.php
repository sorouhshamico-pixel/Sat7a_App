<?php

namespace App\Domain\Users\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case PendingVerification = 'pending_verification';
}
