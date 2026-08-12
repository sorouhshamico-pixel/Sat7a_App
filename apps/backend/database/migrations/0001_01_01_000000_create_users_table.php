<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // Customers and provider-side staff (owner/fleet manager/driver/...)
            // authenticate via phone + OTP; admin-side staff authenticate via
            // email + password + MFA. Both columns are nullable because no
            // single user type needs both (see docs/SECURITY.md §Authentication).
            $table->string('name')->nullable();
            $table->string('phone')->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();

            $table->string('user_type');
            $table->string('status')->default('active');
            $table->string('locale', 5)->default('ar');

            // Admin MFA (TOTP) — see docs/SECURITY.md §Authentication. Values
            // are encrypted at rest via the model's cast, never logged.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index('user_type');
        });

        // Enums are stored as varchar + a CHECK constraint rather than a
        // native Postgres ENUM type, so adding a new case later is a plain
        // migration instead of a type alteration (see docs/DATABASE_SCHEMA.md).
        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_user_type_check CHECK (user_type IN ('customer', 'provider_staff', 'admin_staff'))"
        );
        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'suspended', 'pending_verification'))"
        );

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
