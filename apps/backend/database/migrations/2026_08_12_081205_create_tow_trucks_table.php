<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tow_trucks', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            // A driver can only be behind the wheel of one truck at a time.
            $table->foreignId('driver_id')->nullable()->unique()->constrained('drivers')->nullOnDelete();

            $table->string('manufacturer');
            $table->string('model');
            $table->unsignedSmallInteger('year');
            $table->string('plate_number')->unique();
            $table->string('capacity')->nullable();

            // List of ServiceCapability enum values this truck supports —
            // JSON so a truck can support more than one (see
            // docs/DATABASE_SCHEMA.md and docs/COMPLIANCE.md). Validated
            // against the enum in the Form Request, not a DB constraint,
            // since Postgres CHECK doesn't reach into JSON array elements
            // cheaply.
            $table->json('service_capabilities');

            $table->string('status')->default('offline');

            // Plain lat/lng for now — converted to a PostGIS geography
            // column once spatial "nearby" queries land in Phase 6 (see
            // docs/ARCHITECTURE.md §6 and docs/DEPLOYMENT.md for the
            // pending PostGIS activation step on this dev machine).
            $table->decimal('current_latitude', 10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();
            $table->timestamp('last_location_at')->nullable();

            $table->timestamps();

            $table->index(['provider_id', 'status']);
        });

        DB::statement(
            "ALTER TABLE tow_trucks ADD CONSTRAINT tow_trucks_status_check CHECK (status IN ('offline', 'available', 'reserved', 'en_route', 'arrived', 'loading', 'on_trip', 'unavailable', 'maintenance', 'suspended'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('tow_trucks');
    }
};
