<?php

namespace App\Domain\Fleet\Enums;

/**
 * See spec §25 "أنواع الخدمة". A truck can support more than one — stored
 * as a JSON array on `tow_trucks.service_capabilities`. Adding a new
 * capability is an additive enum case, never a breaking schema change.
 */
enum ServiceCapability: string
{
    case StandardFlatbed = 'standard_flatbed';
    case HydraulicFlatbed = 'hydraulic_flatbed';
    case LuxuryVehicle = 'luxury_vehicle';
    case LowClearanceVehicle = 'low_clearance_vehicle';
    case AccidentRecovery = 'accident_recovery';
    case HeavyVehicle = 'heavy_vehicle';
}
