<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            // The driver's own login identity (phone + OTP, see
            // docs/SECURITY.md §Authentication) — name/phone live on the
            // user record, never duplicated here.
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            // Collected only where legally/operationally needed — see
            // docs/SECURITY.md §Data classification.
            $table->string('nationality')->nullable();
            $table->string('license_number')->nullable();
            $table->date('license_expires_at')->nullable();

            $table->string('status')->default('active');
            $table->boolean('is_available')->default(false);
            $table->decimal('rating', 3, 2)->nullable();

            $table->timestamps();

            $table->index(['provider_id', 'status']);
        });

        DB::statement(
            "ALTER TABLE drivers ADD CONSTRAINT drivers_status_check CHECK (status IN ('active', 'inactive', 'suspended'))"
        );
        DB::statement(
            'ALTER TABLE drivers ADD CONSTRAINT drivers_rating_range_check CHECK (rating IS NULL OR (rating >= 0 AND rating <= 5))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
