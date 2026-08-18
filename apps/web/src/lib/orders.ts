// Mirrors App\Domain\Orders\Enums\OrderStatus (apps/backend) — kept as a
// plain lookup table here rather than importing from the backend, since
// the two apps don't share a build.
export const ORDER_STATUS_LABELS: Record<string, string> = {
  pending: "قيد الانتظار",
  searching_provider: "جارٍ البحث عن مزود",
  provider_assigned: "تم تعيين مزود",
  provider_en_route: "السائق في الطريق",
  provider_arrived: "وصل السائق",
  vehicle_loading: "جارٍ تحميل المركبة",
  trip_started: "بدأت الرحلة",
  in_transit: "في الطريق",
  vehicle_delivered: "تم توصيل المركبة",
  completed: "مكتمل",
  cancelled_by_customer: "ألغاه العميل",
  cancelled_by_provider: "ألغاه المزود",
  cancelled_by_admin: "ألغاه الإدارة",
  expired: "منتهي",
  disputed: "متنازع عليه",
  refund_pending: "بانتظار الاسترداد",
  refunded: "تم الاسترداد",
};

type BadgeTone = "neutral" | "success" | "warning" | "danger" | "info";

export function orderStatusTone(status: string): BadgeTone {
  if (status === "completed") return "success";
  if (status.startsWith("cancelled") || status === "expired") return "danger";
  if (status === "disputed" || status === "refund_pending") return "warning";
  if (status === "pending" || status === "searching_provider") return "neutral";

  return "info";
}

export function orderStatusLabel(status: string): string {
  return ORDER_STATUS_LABELS[status] ?? status;
}

export const DISPATCHABLE_ORDER_STATUSES = new Set(["pending", "searching_provider"]);

export const CANCELLABLE_ORDER_STATUSES = new Set([
  "pending",
  "searching_provider",
  "provider_assigned",
  "provider_en_route",
  "provider_arrived",
  "vehicle_loading",
]);
