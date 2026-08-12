<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Pricing\Models\PricingRuleVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PricingRuleVersion
 */
class PricingRuleVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'version_label' => $this->version_label,
            'base_fee' => $this->base_fee,
            'minimum_fare' => $this->minimum_fare,
            'distance_rate_per_km' => $this->distance_rate_per_km,
            'service_type_fees' => $this->service_type_fees,
            'vehicle_category_multipliers' => $this->vehicle_category_multipliers,
            'night_fee' => $this->night_fee,
            'night_start_hour' => $this->night_start_hour,
            'night_end_hour' => $this->night_end_hour,
            'waiting_fee_per_minute' => $this->waiting_fee_per_minute,
            'free_waiting_minutes' => $this->free_waiting_minutes,
            'zone_fee' => $this->zone_fee,
            'special_condition_fee' => $this->special_condition_fee,
            'platform_service_fee_percentage' => $this->platform_service_fee_percentage,
            'vat_percentage' => $this->vat_percentage,
            'is_active' => $this->is_active,
            'effective_from' => $this->effective_from,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
