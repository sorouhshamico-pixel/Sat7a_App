<?php

namespace App\Domain\Compliance\Enums;

/**
 * See docs/COMPLIANCE.md §Documents. `vehicle_registration`, `driver_license`,
 * and `identity` are named now but only become attachable to a real owner
 * (tow truck / driver) once the Fleet & Drivers domain lands in Phase 4 —
 * the catalog is stable so that migration doesn't need a schema change.
 */
enum DocumentType: string
{
    case CommercialRegistration = 'commercial_registration';
    case ActivityLicense = 'activity_license';
    case VehicleRegistration = 'vehicle_registration';
    case Insurance = 'insurance';
    case DriverLicense = 'driver_license';
    case Identity = 'identity';
    case BankProof = 'bank_proof';

    /**
     * Identity documents are the most sensitive class — gated behind
     * `documents.view_sensitive`, not just `documents.view` (see
     * docs/SECURITY.md §Data classification).
     */
    public function isHighlySensitive(): bool
    {
        return $this === self::Identity;
    }
}
