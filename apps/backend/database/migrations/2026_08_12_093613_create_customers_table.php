<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // Name/phone/email/locale/status/registration date all already
            // live on `users` (see docs/SECURITY.md §Authentication) — this
            // table only holds what's genuinely customer-domain-specific,
            // matching the same split used for Provider/Driver profiles.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('avatar_path')->nullable();
            $table->json('preferences')->nullable();
            $table->json('notification_preferences')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
