<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Single source of truth for "which provider does this
            // provider_staff user belong to" — covers the owner, fleet
            // managers, and drivers alike, so every provider-scoped
            // endpoint ("my provider", "my fleet", "my drivers") resolves
            // the same way regardless of the caller's role (see
            // docs/COMPLIANCE.md and docs/DATABASE_SCHEMA.md). Null for
            // customers and admin_staff.
            $table->foreignId('provider_id')->nullable()->after('user_type')
                ->constrained('providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('provider_id');
        });
    }
};
