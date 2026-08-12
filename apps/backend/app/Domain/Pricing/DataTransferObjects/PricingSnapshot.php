<?php

namespace App\Domain\Pricing\DataTransferObjects;

use App\Domain\Pricing\Enums\PriceType;

/**
 * Every order stores one of these verbatim (see docs/DATABASE_SCHEMA.md
 * §Immutability and docs/PRICING_ENGINE.md) — historical orders never
 * change when pricing rules change later, because they don't reference
 * the live rules at all, only this frozen breakdown.
 */
readonly class PricingSnapshot
{
    public function __construct(
        public string $pricingRuleVersionId,
        public string $versionLabel,
        public PriceType $priceType,
        public int $distanceMeters,
        public int $baseFee,
        public int $distanceFee,
        public int $serviceTypeFee,
        public float $vehicleCategoryMultiplier,
        public int $nightFee,
        public int $waitingFee,
        public int $zoneFee,
        public int $specialConditionFee,
        public int $subtotalBeforePlatformFee,
        public int $platformServiceFee,
        public int $subtotal,
        public int $discount,
        public int $taxableAmount,
        public float $vatPercentage,
        public int $vatAmount,
        public int $total,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pricing_rule_version_id' => $this->pricingRuleVersionId,
            'version_label' => $this->versionLabel,
            'price_type' => $this->priceType->value,
            'distance_meters' => $this->distanceMeters,
            'base_fee' => $this->baseFee,
            'distance_fee' => $this->distanceFee,
            'service_type_fee' => $this->serviceTypeFee,
            'vehicle_category_multiplier' => $this->vehicleCategoryMultiplier,
            'night_fee' => $this->nightFee,
            'waiting_fee' => $this->waitingFee,
            'zone_fee' => $this->zoneFee,
            'special_condition_fee' => $this->specialConditionFee,
            'subtotal_before_platform_fee' => $this->subtotalBeforePlatformFee,
            'platform_service_fee' => $this->platformServiceFee,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'taxable_amount' => $this->taxableAmount,
            'vat_percentage' => $this->vatPercentage,
            'vat_amount' => $this->vatAmount,
            'total' => $this->total,
        ];
    }
}
