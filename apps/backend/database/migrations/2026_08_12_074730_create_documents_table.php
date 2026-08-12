<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            // Polymorphic: providers today, drivers/tow trucks from Phase 4
            // reuse the same table and verification workflow (see
            // docs/COMPLIANCE.md) instead of a schema rewrite.
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');

            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();

            $table->string('document_type');
            $table->string('document_number')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();

            $table->string('verification_status')->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_reason')->nullable();

            // Never a public URL — private disk, served only via a
            // permission-checked signed download route (see
            // docs/SECURITY.md §File uploads).
            $table->string('storage_path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');

            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id']);
            $table->index('expires_at');
        });

        DB::statement(
            "ALTER TABLE documents ADD CONSTRAINT documents_verification_status_check CHECK (verification_status IN ('pending', 'verified', 'rejected'))"
        );
        DB::statement(
            "ALTER TABLE documents ADD CONSTRAINT documents_document_type_check CHECK (document_type IN ('commercial_registration', 'activity_license', 'vehicle_registration', 'insurance', 'driver_license', 'identity', 'bank_proof'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
