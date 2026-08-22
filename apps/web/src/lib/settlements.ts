// Mirrors App\Domain\Ledger\Enums\SettlementStatus (apps/backend).
export const SETTLEMENT_STATUS_LABELS: Record<string, string> = {
  draft: "مسودة",
  pending_approval: "بانتظار الاعتماد",
  approved: "معتمد",
  processing: "قيد المعالجة",
  paid: "مدفوع",
  failed: "فشل",
  cancelled: "ملغى",
};

type BadgeTone = "neutral" | "success" | "warning" | "danger" | "info";

export function settlementStatusTone(status: string): BadgeTone {
  if (status === "paid") return "success";
  if (status === "failed" || status === "cancelled") return "danger";
  if (status === "draft") return "neutral";

  return "info";
}

// The single generic status-advance endpoint only accepts the next step
// in App\Domain\Ledger\Enums\SettlementStatus::allowedTransitions() — this
// mirrors that matrix so the UI only ever offers a legal next status.
export const SETTLEMENT_NEXT_STATUS: Record<string, string[]> = {
  draft: ["pending_approval", "cancelled"],
  pending_approval: ["approved", "cancelled"],
  approved: ["processing", "cancelled"],
  processing: ["paid", "failed"],
  paid: [],
  failed: [],
  cancelled: [],
};
