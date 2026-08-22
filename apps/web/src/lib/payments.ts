// Mirrors App\Domain\Payments\Enums\{PaymentStatus,PaymentMethod,RefundStatus}
// (apps/backend).
export const PAYMENT_STATUS_LABELS: Record<string, string> = {
  pending: "قيد الانتظار",
  authorized: "مصرّح به",
  captured: "مكتمل",
  failed: "فشل",
  cancelled: "ملغى",
  partially_refunded: "مسترد جزئياً",
  refunded: "مسترد بالكامل",
};

export const PAYMENT_METHOD_LABELS: Record<string, string> = {
  mada: "مدى",
  visa: "فيزا",
  mastercard: "ماستركارد",
  apple_pay: "Apple Pay",
  cash: "نقداً",
};

export const REFUND_STATUS_LABELS: Record<string, string> = {
  pending: "قيد الانتظار",
  succeeded: "تم",
  failed: "فشل",
};

type BadgeTone = "neutral" | "success" | "warning" | "danger" | "info";

export function paymentStatusTone(status: string): BadgeTone {
  if (status === "captured" || status === "refunded") return "success";
  if (status === "failed" || status === "cancelled") return "danger";
  if (status === "partially_refunded") return "warning";

  return "neutral";
}
