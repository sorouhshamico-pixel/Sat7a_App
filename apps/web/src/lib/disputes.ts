// Mirrors App\Domain\Disputes\Enums\DisputeReason / DisputeStatus (apps/backend).
export const DISPUTE_REASON_LABELS: Record<string, string> = {
  overcharge: "رسوم زائدة",
  service_quality: "جودة الخدمة",
  damage: "تلف",
  no_show: "عدم حضور السائق",
  other: "أخرى",
};

export const DISPUTE_STATUS_LABELS: Record<string, string> = {
  open: "مفتوح",
  under_review: "قيد المراجعة",
  resolved: "تم الحل",
  rejected: "مرفوض",
};

type BadgeTone = "neutral" | "success" | "warning" | "danger" | "info";

export function disputeStatusTone(status: string): BadgeTone {
  if (status === "resolved") return "success";
  if (status === "rejected") return "danger";
  if (status === "under_review") return "info";

  return "warning";
}
