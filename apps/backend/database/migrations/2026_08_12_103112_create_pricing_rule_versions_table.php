<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production pricing is never hardcoded — every rate lives here, editable
 * only via a permissioned, audited action (see docs/PRICING_ENGINE.md and
 * docs/ROLES_PERMISSIONS.md). Money columns are integer minor units
 * (halalas), never float (see docs/DATABASE_SCHEMA.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->string('version_label')->unique();

            $table->unsignedInteger('base_fee');
            $table->unsignedInteger('minimum_fare');
            $table->unsignedInteger('distance_rate_per_km');

            // Keyed by App\Domain\Fleet\Enums\ServiceCapability — a missing
            // key means no extra fee for that service type.
            $table->json('service_type_fees');

            // Keyed by App\Domain\Pricing\Enums\VehicleCategory — a
            // missing key means a 1.0 (no-op) multiplier.
            $table->json('vehicle_category_multipliers');

            $table->unsignedInteger('night_fee')->default(0);
            $table->unsignedTinyInteger('night_start_hour')->default(22);
            $table->unsignedTinyInteger('night_end_hour')->default(6);

            $table->unsignedInteger('waiting_fee_per_minute')->default(0);
            $table->unsignedInteger('free_waiting_minutes')->default(5);

            // Reserved for Phase 6's deferred PostGIS service zones — 0
            // until zone-based pricing is wired up (see docs/ROADMAP.md
            // Phase 6).
            $table->unsignedInteger('zone_fee')->default(0);

            $table->unsignedInteger('special_condition_fee')->default(0);

            $table->decimal('platform_service_fee_percentage', 6, 4)->default(0);
            $table->decimal('vat_percentage', 6, 4)->default(0.15);

            $table->boolean('is_active')->default(false);
            $table->timestamp('effective_from')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rule_versions');
    }
};
