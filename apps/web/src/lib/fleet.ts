// Mirrors App\Domain\Fleet\Enums\TowTruckStatus (apps/backend).
export const TOW_TRUCK_STATUS_LABELS: Record<string, string> = {
  offline: "غير متصل",
  available: "متاح",
  reserved: "محجوز",
  en_route: "في الطريق",
  arrived: "وصل",
  loading: "جارٍ التحميل",
  on_trip: "في رحلة",
  unavailable: "غير متاح",
  maintenance: "صيانة",
  suspended: "موقوف",
};

type BadgeTone = "neutral" | "success" | "warning" | "danger" | "info";

export function towTruckStatusTone(status: string): BadgeTone {
  if (status === "available") return "success";
  if (status === "suspended" || status === "unavailable") return "danger";
  if (status === "maintenance" || status === "offline") return "neutral";

  return "info";
}

// Mirrors TowTruckStatus::allowedTransitions() — the status-update endpoint
// only accepts one of these, so the UI only ever offers a legal next
// status (same approach as src/lib/settlements.ts's SETTLEMENT_NEXT_STATUS).
// Only offline/available/maintenance/unavailable are meant to be set
// directly by a provider — the rest (reserved/en_route/arrived/loading/
// on_trip) are set by the dispatch/trip system, and suspended is
// compliance-only, but they're mirrored here in full for accuracy.
export const TOW_TRUCK_NEXT_STATUSES: Record<string, string[]> = {
  offline: ["available", "maintenance"],
  available: ["offline", "maintenance"],
  reserved: [],
  en_route: [],
  arrived: [],
  loading: [],
  on_trip: [],
  unavailable: ["offline", "available"],
  maintenance: ["offline", "available"],
  suspended: [],
};
