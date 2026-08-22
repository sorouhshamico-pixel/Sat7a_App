// Mirrors App\Domain\Providers\Enums\ProviderStatus and
// App\Domain\Compliance\Enums\{DocumentType,DocumentVerificationStatus}
// (apps/backend).
export const PROVIDER_STATUS_LABELS: Record<string, string> = {
  pending: "قيد الانتظار",
  under_review: "قيد المراجعة",
  approved: "معتمد",
  rejected: "مرفوض",
  suspended: "موقوف",
  inactive: "غير نشط",
};

export const DOCUMENT_TYPE_LABELS: Record<string, string> = {
  commercial_registration: "السجل التجاري",
  activity_license: "رخصة النشاط",
  vehicle_registration: "استمارة المركبة",
  insurance: "التأمين",
  driver_license: "رخصة القيادة",
  identity: "الهوية",
  bank_proof: "إثبات الحساب البنكي",
};

export const DOCUMENT_STATUS_LABELS: Record<string, string> = {
  pending: "قيد المراجعة",
  verified: "موثّق",
  rejected: "مرفوض",
};

type BadgeTone = "neutral" | "success" | "warning" | "danger" | "info";

export function providerStatusTone(status: string): BadgeTone {
  if (status === "approved") return "success";
  if (status === "rejected" || status === "suspended") return "danger";
  if (status === "under_review") return "info";
  if (status === "inactive") return "neutral";

  return "warning";
}

export function documentStatusTone(status: string): BadgeTone {
  if (status === "verified") return "success";
  if (status === "rejected") return "danger";

  return "warning";
}
