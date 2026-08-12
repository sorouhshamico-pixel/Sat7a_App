<?php

namespace Tests\Unit\Domain\Pricing;

use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Pricing\Actions\GenerateQuoteAction;
use App\Domain\Pricing\Enums\PriceType;
use App\Domain\Pricing\Enums\VehicleCategory;
use App\Domain\Pricing\Exceptions\PricingException;
use App\Domain\Pricing\Models\PricingRuleVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GenerateQuoteActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeActiveVersion(array $overrides = []): PricingRuleVersion
    {
        $admin = User::factory()->admin()->create();

        $version = new PricingRuleVersion(array_merge([
            'version_label' => 'v1-test-'.uniqid(),
            'base_fee' => 5000,
            'minimum_fare' => 8000,
            'distance_rate_per_km' => 200,
            'service_type_fees' => [ServiceCapability::StandardFlatbed->value => 1000],
            'vehicle_category_multipliers' => [
                VehicleCategory::Sedan->value => 1.0,
                VehicleCategory::Luxury->value => 1.5,
            ],
            'night_fee' => 1500,
            'night_start_hour' => 22,
            'night_end_hour' => 6,
            'waiting_fee_per_minute' => 100,
            'free_waiting_minutes' => 5,
            'zone_fee' => 0,
            'special_condition_fee' => 0,
            'platform_service_fee_percentage' => 0.10,
            'vat_percentage' => 0.15,
            'is_active' => true,
        ], $overrides));
        $version->created_by = $admin->id;
        $version->save();

        return $version;
    }

    public function test_throws_when_no_active_version_exists(): void
    {
        $this->expectException(PricingException::class);

        app(GenerateQuoteAction::class)->handle(10000, ServiceCapability::StandardFlatbed->value, VehicleCategory::Sedan->value);
    }

    public function test_computes_the_exact_expected_total_for_a_daytime_quote(): void
    {
        $this->makeActiveVersion();

        $snapshot = app(GenerateQuoteAction::class)->handle(
            distanceMeters: 10000,
            serviceType: ServiceCapability::StandardFlatbed->value,
            vehicleCategory: VehicleCategory::Sedan->value,
            waitingMinutes: 0,
            at: Carbon::parse('2026-06-01 12:00:00', 'Asia/Riyadh'),
        );

        $this->assertSame(PriceType::FixedQuote, $snapshot->priceType);
        $this->assertSame(5000, $snapshot->baseFee);
        $this->assertSame(2000, $snapshot->distanceFee);
        $this->assertSame(1000, $snapshot->serviceTypeFee);
        $this->assertSame(0, $snapshot->nightFee);
        $this->assertSame(0, $snapshot->waitingFee);
        $this->assertSame(8000, $snapshot->subtotalBeforePlatformFee);
        $this->assertSame(800, $snapshot->platformServiceFee);
        $this->assertSame(8800, $snapshot->subtotal);
        $this->assertSame(1320, $snapshot->vatAmount);
        $this->assertSame(10120, $snapshot->total);
    }

    public function test_applies_night_fee_within_the_configured_window(): void
    {
        $this->makeActiveVersion();

        $snapshot = app(GenerateQuoteAction::class)->handle(
            distanceMeters: 10000,
            serviceType: ServiceCapability::StandardFlatbed->value,
            vehicleCategory: VehicleCategory::Sedan->value,
            at: Carbon::parse('2026-06-01 23:00:00', 'Asia/Riyadh'),
        );

        $this->assertSame(1500, $snapshot->nightFee);
    }

    public function test_does_not_apply_night_fee_just_outside_the_window(): void
    {
        $this->makeActiveVersion();

        $snapshot = app(GenerateQuoteAction::class)->handle(
            distanceMeters: 10000,
            serviceType: ServiceCapability::StandardFlatbed->value,
            vehicleCategory: VehicleCategory::Sedan->value,
            at: Carbon::parse('2026-06-01 21:00:00', 'Asia/Riyadh'),
        );

        $this->assertSame(0, $snapshot->nightFee);
    }

    public function test_waiting_fee_only_applies_beyond_the_free_minutes(): void
    {
        $this->makeActiveVersion();

        $snapshot = app(GenerateQuoteAction::class)->handle(
            distanceMeters: 1000,
            serviceType: ServiceCapability::StandardFlatbed->value,
            vehicleCategory: VehicleCategory::Sedan->value,
            waitingMinutes: 12,
            at: Carbon::parse('2026-06-01 12:00:00', 'Asia/Riyadh'),
        );

        // 12 - 5 free minutes = 7 billable minutes * 100 halalas.
        $this->assertSame(700, $snapshot->waitingFee);
    }

    public function test_vehicle_multiplier_is_applied_after_the_component_sum(): void
    {
        $this->makeActiveVersion();

        $snapshot = app(GenerateQuoteAction::class)->handle(
            distanceMeters: 10000,
            serviceType: ServiceCapability::StandardFlatbed->value,
            vehicleCategory: VehicleCategory::Luxury->value,
            at: Carbon::parse('2026-06-01 12:00:00', 'Asia/Riyadh'),
        );

        // subtotalBeforeMultiplier = 8000, * 1.5 luxury multiplier = 12000.
        $this->assertSame(12000, $snapshot->subtotalBeforePlatformFee);
    }

    public function test_minimum_fare_is_enforced(): void
    {
        $this->makeActiveVersion(['minimum_fare' => 50000]);

        $snapshot = app(GenerateQuoteAction::class)->handle(
            distanceMeters: 1000,
            serviceType: ServiceCapability::StandardFlatbed->value,
            vehicleCategory: VehicleCategory::Sedan->value,
            at: Carbon::parse('2026-06-01 12:00:00', 'Asia/Riyadh'),
        );

        $this->assertSame(50000, $snapshot->subtotalBeforePlatformFee);
    }

    public function test_unknown_service_type_and_vehicle_category_default_to_no_extra_charge(): void
    {
        $this->makeActiveVersion();

        $snapshot = app(GenerateQuoteAction::class)->handle(
            distanceMeters: 1000,
            serviceType: ServiceCapability::HeavyVehicle->value,
            vehicleCategory: VehicleCategory::Van->value,
            at: Carbon::parse('2026-06-01 12:00:00', 'Asia/Riyadh'),
        );

        $this->assertSame(0, $snapshot->serviceTypeFee);
        $this->assertSame(1.0, $snapshot->vehicleCategoryMultiplier);
    }
}
