<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * See docs/ORDER_LIFECYCLE.md. Provider/driver/tow-truck assignment
 * columns are nullable and unused until Phase 9 (Dispatch); payment
 * columns are nullable and unused until Phase 12 (Payments) — added now
 * so those phases are additive, not a schema rewrite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();

            $table->string('service_type');

            $table->decimal('pickup_latitude', 10, 7);
            $table->decimal('pickup_longitude', 10, 7);
            $table->string('pickup_formatted_address');

            $table->decimal('dropoff_latitude', 10, 7);
            $table->decimal('dropoff_longitude', 10, 7);
            $table->string('dropoff_formatted_address');

            $table->string('notes')->nullable();

            $table->string('status')->default('pending');

            // The exact rule-version/fee breakdown used at quote time —
            // never recalculated later (see docs/DATABASE_SCHEMA.md
            // §Immutability).
            $table->json('pricing_snapshot');
            $table->unsignedInteger('quoted_price');
            $table->unsignedInteger('final_price')->nullable();

            $table->string('payment_method')->default('cash');

            $table->foreignId('assigned_provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->foreignId('assigned_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('assigned_tow_truck_id')->nullable()->constrained('tow_trucks')->nullOnDelete();

            $table->string('cancelled_by')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->unsignedInteger('cancellation_fee')->default(0);

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('trip_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index('status');
        });

        DB::statement(
            "ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('pending', 'searching_provider', 'provider_assigned', 'provider_en_route', 'provider_arrived', 'vehicle_loading', 'trip_started', 'in_transit', 'vehicle_delivered', 'completed', 'cancelled_by_customer', 'cancelled_by_provider', 'cancelled_by_admin', 'expired', 'disputed', 'refund_pending', 'refunded'))"
        );
        DB::statement(
            "ALTER TABLE orders ADD CONSTRAINT orders_payment_method_check CHECK (payment_method IN ('cash', 'card'))"
        );
        DB::statement(
            "ALTER TABLE orders ADD CONSTRAINT orders_cancelled_by_check CHECK (cancelled_by IS NULL OR cancelled_by IN ('customer', 'provider', 'admin', 'system'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
