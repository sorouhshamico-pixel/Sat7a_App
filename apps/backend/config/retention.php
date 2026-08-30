<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data Retention Windows
    |--------------------------------------------------------------------------
    |
    | See docs/SECURITY.md §Data retention: windows are configurable, never
    | hardcoded in the command that enforces them. This file only covers the
    | baseline hygiene purges implemented so far (App\Console\Commands\
    | PurgeExpiredDataCommand) — OTP codes and raw GPS location history, both
    | flagged "highly sensitive"/privacy-relevant in this document's Data
    | classification section. It deliberately does not attempt the full
    | account-deletion workflow (request → identity confirmation →
    | legal/financial retention check → anonymization) described there —
    | that remains a distinct, larger compliance feature.
    |
    */

    // An OTP code is functionally dead the moment it expires (5 minutes,
    // see App\Domain\Authentication\Models\OtpCode) or is consumed — this
    // window is purely a short fraud-investigation grace period, not a
    // functional requirement.
    'otp_codes_hours' => env('RETENTION_OTP_CODES_HOURS', 24),

    // Raw per-second GPS breadcrumbs (App\Domain\Tracking\Models\
    // OrderLocationPing) — real location-history privacy exposure if kept
    // indefinitely. 90 days comfortably outlives any dispute-investigation
    // or support-ticket need.
    'location_pings_days' => env('RETENTION_LOCATION_PINGS_DAYS', 90),

];
