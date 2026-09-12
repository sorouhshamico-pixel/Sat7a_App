// Mirrors App\Domain\Orders\Actions\AdvanceTripStatusAction::DRIVER_ADVANCEABLE_STATUSES
// (apps/backend) — the single next legal status a driver can advance an
// active trip to, so the UI only ever offers one button rather than a
// free-form status picker. `provider_assigned` (set by dispatch, not the
// driver) is the entry point; `completed` is terminal.
export const TRIP_NEXT_STATUS: Record<string, string | null> = {
  provider_assigned: "provider_en_route",
  provider_en_route: "provider_arrived",
  provider_arrived: "vehicle_loading",
  vehicle_loading: "trip_started",
  trip_started: "in_transit",
  in_transit: "vehicle_delivered",
  vehicle_delivered: "completed",
  completed: null,
};

// Mirrors App\Domain\Orders\Enums\OrderStatus::allowedTransitions() — the
// statuses from which `CancelledByProvider` is a valid target. A trip
// already underway (`trip_started` onward) can no longer be cancelled by
// the driver, since the vehicle may already be loaded.
const DRIVER_CANCELLABLE_STATUSES = new Set([
  "provider_assigned",
  "provider_en_route",
  "provider_arrived",
  "vehicle_loading",
]);

export function isDriverCancellable(status: string): boolean {
  return DRIVER_CANCELLABLE_STATUSES.has(status);
}

// Any status where the driver's active-order card is done being actionable
// — either the driver completed it, or the order was cancelled (by the
// driver themselves, the customer, or an admin — all three are possible
// while a driver holds an assigned order, not just the provider-cancel
// case this same phase added an endpoint for).
const TRIP_OVER_STATUSES = new Set([
  "completed",
  "cancelled_by_customer",
  "cancelled_by_provider",
  "cancelled_by_admin",
]);

export function isTripOver(status: string): boolean {
  return TRIP_OVER_STATUSES.has(status);
}

export const TRIP_NEXT_STATUS_LABEL: Record<string, string> = {
  provider_en_route: "بدء التوجه إلى الموقع",
  provider_arrived: "تأكيد الوصول",
  vehicle_loading: "بدء تحميل المركبة",
  trip_started: "بدء الرحلة",
  in_transit: "في الطريق إلى الوجهة",
  vehicle_delivered: "تأكيد توصيل المركبة",
  completed: "إنهاء الطلب",
};
