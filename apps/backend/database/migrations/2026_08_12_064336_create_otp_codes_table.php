<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('user_type');
            // Hashed, never plain text (see docs/SECURITY.md §OTP handling).
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->ipAddress('requested_ip')->nullable();
            $table->timestamps();

            $table->index(['phone', 'user_type']);
        });

        // OTP login only applies to phone-authenticated account types —
        // admin_staff never reaches this table (see docs/SECURITY.md
        // §Authentication).
        DB::statement(
            "ALTER TABLE otp_codes ADD CONSTRAINT otp_codes_user_type_check CHECK (user_type IN ('customer', 'provider_staff'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
