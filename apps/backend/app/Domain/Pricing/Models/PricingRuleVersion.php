<?php

namespace App\Domain\Pricing\Models;

use App\Models\User;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<string, int> $service_type_fees
 * @property array<string, float> $vehicle_category_multipliers
 * @property Carbon|null $effective_from
 */
#[Fillable([
    'version_label', 'base_fee', 'minimum_fare', 'distance_rate_per_km',
    'service_type_fees', 'vehicle_category_multipliers',
    'night_fee', 'night_start_hour', 'night_end_hour',
    'waiting_fee_per_minute', 'free_waiting_minutes',
    'zone_fee', 'special_condition_fee',
    'platform_service_fee_percentage', 'vat_percentage',
    'is_active', 'effective_from', 'notes',
])]
class PricingRuleVersion extends Model
{
    use HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_type_fees' => 'array',
            'vehicle_category_multipliers' => 'array',
            'platform_service_fee_percentage' => 'decimal:4',
            'vat_percentage' => 'decimal:4',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function serviceTypeFee(string $serviceType): int
    {
        return (int) ($this->service_type_fees[$serviceType] ?? 0);
    }

    public function vehicleCategoryMultiplier(string $vehicleCategory): float
    {
        return (float) ($this->vehicle_category_multipliers[$vehicleCategory] ?? 1.0);
    }
}
