<?php

namespace Tests\Feature\Api\V1\Pricing;

use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Pricing\Enums\VehicleCategory;
use App\Domain\Pricing\Models\PricingRuleVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    private function seedActiveVersion(): void
    {
        $admin = User::factory()->admin()->create();

        $version = new PricingRuleVersion([
            'version_label' => 'v1',
            'base_fee' => 5000,
            'minimum_fare' => 8000,
            'distance_rate_per_km' => 200,
            'service_type_fees' => [ServiceCapability::StandardFlatbed->value => 1000],
            'vehicle_category_multipliers' => [VehicleCategory::Sedan->value => 1.0],
            'platform_service_fee_percentage' => 0.10,
            'vat_percentage' => 0.15,
            'is_active' => true,
        ]);
        $version->created_by = $admin->id;
        $version->save();
    }

    public function test_quote_is_public_and_requires_no_authentication(): void
    {
        $this->seedActiveVersion();

        $response = $this->postJson('/api/v1/pricing/quote', [
            'distance_meters' => 10000,
            'service_type' => ServiceCapability::StandardFlatbed->value,
            'vehicle_category' => VehicleCategory::Sedan->value,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.price_type', 'fixed_quote');
        $this->assertGreaterThan(0, $response->json('data.total'));
    }

    public function test_quote_fails_gracefully_when_no_active_pricing_exists(): void
    {
        $response = $this->postJson('/api/v1/pricing/quote', [
            'distance_meters' => 10000,
            'service_type' => ServiceCapability::StandardFlatbed->value,
            'vehicle_category' => VehicleCategory::Sedan->value,
        ]);

        $response->assertStatus(503);
        $response->assertJsonPath('errors.0.code', 'PRICING_UNAVAILABLE');
    }

    public function test_manual_quote_flag_skips_automated_pricing(): void
    {
        $this->seedActiveVersion();

        $response = $this->postJson('/api/v1/pricing/quote', [
            'distance_meters' => 10000,
            'service_type' => ServiceCapability::StandardFlatbed->value,
            'vehicle_category' => VehicleCategory::Sedan->value,
            'requires_manual_quote' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.price_type', 'manual_quote');
        $this->assertArrayNotHasKey('total', $response->json('data'));
    }

    public function test_quote_validates_required_fields(): void
    {
        $this->postJson('/api/v1/pricing/quote', [])->assertStatus(422);
    }

    public function test_quote_rejects_an_invalid_service_type(): void
    {
        $this->seedActiveVersion();

        $response = $this->postJson('/api/v1/pricing/quote', [
            'distance_meters' => 10000,
            'service_type' => 'not_a_real_type',
            'vehicle_category' => VehicleCategory::Sedan->value,
        ]);

        $response->assertStatus(422);
    }

    public function test_quote_endpoint_is_rate_limited(): void
    {
        $this->seedActiveVersion();

        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/pricing/quote', [
                'distance_meters' => 1000,
                'service_type' => ServiceCapability::StandardFlatbed->value,
                'vehicle_category' => VehicleCategory::Sedan->value,
            ]);
        }

        $response = $this->postJson('/api/v1/pricing/quote', [
            'distance_meters' => 1000,
            'service_type' => ServiceCapability::StandardFlatbed->value,
            'vehicle_category' => VehicleCategory::Sedan->value,
        ]);

        $response->assertStatus(429);
    }
}
