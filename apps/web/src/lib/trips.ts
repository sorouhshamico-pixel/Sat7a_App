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

export const TRIP_NEXT_STATUS_LABEL: Record<string, string> = {
  provider_en_route: "بدء التوجه إلى الموقع",
  provider_arrived: "تأكيد الوصول",
  vehicle_loading: "بدء تحميل المركبة",
  trip_started: "بدء الرحلة",
  in_transit: "في الطريق إلى الوجهة",
  vehicle_delivered: "تأكيد توصيل المركبة",
  completed: "إنهاء الطلب",
};
