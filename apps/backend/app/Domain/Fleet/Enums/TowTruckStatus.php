<?php

namespace App\Domain\Fleet\Enums;

/**
 * Real state machine (see spec §26 and docs/DATABASE_SCHEMA.md) — no
 * transition is allowed unless listed here. `reserved`/`en_route`/
 * `arrived`/`loading`/`on_trip` are set by the dispatch/trip system from
 * Phase 9 onward, not directly by a provider through the fleet API;
 * `suspended` is compliance-only (see
 * App\Domain\Fleet\Actions\SuspendTowTruckAction). Phase 4 only exposes a
 * provider-facing endpoint for the offline/available/maintenance/
 * unavailable subgraph.
 */
enum TowTruckStatus: string
{
    case Offline = 'offline';
    case Available = 'available';
    case Reserved = 'reserved';
    case EnRoute = 'en_route';
    case Arrived = 'arrived';
    case Loading = 'loading';
    case OnTrip = 'on_trip';
    case Unavailable = 'unavailable';
    case Maintenance = 'maintenance';
    case Suspended = 'suspended';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Offline => [self::Available, self::Maintenance],
            self::Available => [self::Offline, self::Reserved, self::Maintenance, self::Unavailable],
            self::Reserved => [self::EnRoute, self::Available],
            self::EnRoute => [self::Arrived, self::Available],
            self::Arrived => [self::Loading, self::Available],
            self::Loading => [self::OnTrip, self::Available],
            self::OnTrip => [self::Available, self::Unavailable],
            self::Unavailable => [self::Offline, self::Available],
            self::Maintenance => [self::Offline, self::Available],
            // Only a compliance action re-activates a suspended truck —
            // see docs/ROLES_PERMISSIONS.md.
            self::Suspended => [self::Offline],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
