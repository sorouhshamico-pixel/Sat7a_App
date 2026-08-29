// Mirrors App\Domain\Pricing\DataTransferObjects\PricingSnapshot::toArray()
// (apps/backend).
export interface PricingSnapshot {
  price_type: string;
  distance_meters: number;
  base_fee: number;
  distance_fee: number;
  service_type_fee: number;
  vehicle_category_multiplier: number;
  night_fee: number;
  waiting_fee: number;
  zone_fee: number;
  special_condition_fee: number;
  subtotal_before_platform_fee: number;
  platform_service_fee: number;
  subtotal: number;
  discount: number;
  taxable_amount: number;
  vat_percentage: number;
  vat_amount: number;
  total: number;
}
