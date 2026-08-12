<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // The Provider Owner user (see docs/PRODUCT_REQUIREMENTS.md
            // §Provider journey and docs/ROLES_PERMISSIONS.md).
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();

            $table->string('business_name');
            $table->string('commercial_registration_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('contact_phone');
            $table->string('contact_email')->nullable();

            $table->string('status')->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
        });

        // Enums stored as varchar + CHECK constraint — see
        // docs/DATABASE_SCHEMA.md.
        DB::statement(
            "ALTER TABLE providers ADD CONSTRAINT providers_status_check CHECK (status IN ('pending', 'under_review', 'approved', 'rejected', 'suspended', 'inactive'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
