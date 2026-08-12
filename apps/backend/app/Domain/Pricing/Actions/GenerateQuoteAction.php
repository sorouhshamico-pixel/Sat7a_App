<?php

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\DataTransferObjects\PricingSnapshot;
use App\Domain\Pricing\Enums\PriceType;
use App\Domain\Pricing\Exceptions\PricingException;
use App\Domain\Pricing\Models\PricingRuleVersion;
use Illuminate\Support\Carbon;

/**
 * Computes a fixed-quote price breakdown from the currently active
 * pricing rules. Never called when the caller has already flagged the
 * situation as needing a manual quote (severely damaged vehicle, no
 * wheels, underground parking, ...) — see docs/PRICING_ENGINE.md
 * §Manual quote and App\Http\Controllers\Api\V1\Pricing\QuoteController,
 * which branches before reaching this action.
 */
class GenerateQuoteAction
{
    public function handle(
        int $distanceMeters,
        string $serviceType,
        string $vehicleCategory,
        int $waitingMinutes = 0,
        ?Carbon $at = null,
    ): PricingSnapshot {
        $version = PricingRuleVersion::query()->where('is_active', true)->first();

        if ($version === null) {
            throw PricingException::noActiveVersion();
        }

        $at ??= now();

        $baseFee = $version->base_fee;
        $distanceFee = (int) round($version->distance_rate_per_km * ($distanceMeters / 1000));
        $serviceTypeFee = $version->serviceTypeFee($serviceType);
        $vehicleMultiplier = $version->vehicleCategoryMultiplier($vehicleCategory);

        $nightFee = $this->isNight($at, $version->night_start_hour, $version->night_end_hour)
            ? $version->night_fee
            : 0;

        $billableWaitingMinutes = max(0, $waitingMinutes - $version->free_waiting_minutes);
        $waitingFee = $billableWaitingMinutes * $version->waiting_fee_per_minute;

        $zoneFee = $version->zone_fee;
        $specialConditionFee = 0;

        $subtotalBeforeMultiplier = $baseFee + $distanceFee + $serviceTypeFee
            + $nightFee + $waitingFee + $zoneFee + $specialConditionFee;

        $subtotalBeforePlatformFee = (int) round($subtotalBeforeMultiplier * $vehicleMultiplier);
        $subtotalBeforePlatformFee = max($subtotalBeforePlatformFee, $version->minimum_fare);

        $platformServiceFee = (int) round($subtotalBeforePlatformFee * (float) $version->platform_service_fee_percentage);
        $subtotal = $subtotalBeforePlatformFee + $platformServiceFee;

        // No coupon/promo system yet (deferred — see docs/ROADMAP.md
        // §Explicitly deferred) — always zero for now, but present in the
        // snapshot so adding one later doesn't change the shape orders
        // already stored.
        $discount = 0;

        $taxableAmount = max(0, $subtotal - $discount);
        $vatPercentage = (float) $version->vat_percentage;
        $vatAmount = (int) round($taxableAmount * $vatPercentage);
        $total = $taxableAmount + $vatAmount;

        return new PricingSnapshot(
            pricingRuleVersionId: $version->public_id,
            versionLabel: $version->version_label,
            priceType: PriceType::FixedQuote,
            distanceMeters: $distanceMeters,
            baseFee: $baseFee,
            distanceFee: $distanceFee,
            serviceTypeFee: $serviceTypeFee,
            vehicleCategoryMultiplier: $vehicleMultiplier,
            nightFee: $nightFee,
            waitingFee: $waitingFee,
            zoneFee: $zoneFee,
            specialConditionFee: $specialConditionFee,
            subtotalBeforePlatformFee: $subtotalBeforePlatformFee,
            platformServiceFee: $platformServiceFee,
            subtotal: $subtotal,
            discount: $discount,
            taxableAmount: $taxableAmount,
            vatPercentage: $vatPercentage,
            vatAmount: $vatAmount,
            total: $total,
        );
    }

    private function isNight(Carbon $at, int $nightStartHour, int $nightEndHour): bool
    {
        $localHour = (int) $at->clone()->setTimezone('Asia/Riyadh')->format('G');

        if ($nightStartHour === $nightEndHour) {
            return false;
        }

        // Night window wraps past midnight (e.g. 22:00 -> 06:00).
        if ($nightStartHour > $nightEndHour) {
            return $localHour >= $nightStartHour || $localHour < $nightEndHour;
        }

        return $localHour >= $nightStartHour && $localHour < $nightEndHour;
    }
}
