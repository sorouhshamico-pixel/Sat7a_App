<?php

namespace App\Domain\Drivers\Enums;

enum DriverStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    // Platform-imposed, not provider-controlled — see
    // App\Domain\Drivers\Actions\SuspendDriverAction.
    case Suspended = 'suspended';
}
