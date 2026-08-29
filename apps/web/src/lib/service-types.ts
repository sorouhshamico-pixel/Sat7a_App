// Mirrors App\Domain\Fleet\Enums\ServiceCapability and
// App\Domain\Pricing\Enums\VehicleCategory (apps/backend).
export const SERVICE_TYPE_LABELS: Record<string, string> = {
  standard_flatbed: "سطحة عادية",
  hydraulic_flatbed: "سطحة هيدروليكية",
  luxury_vehicle: "سيارات فاخرة",
  low_clearance_vehicle: "سيارات منخفضة",
  accident_recovery: "انتشال بعد حادث",
  heavy_vehicle: "مركبات ثقيلة",
};

export const VEHICLE_CATEGORY_LABELS: Record<string, string> = {
  sedan: "سيدان",
  suv: "دفع رباعي",
  van: "فان",
  luxury: "فاخرة",
  heavy: "ثقيلة",
  motorcycle: "دراجة نارية",
};
