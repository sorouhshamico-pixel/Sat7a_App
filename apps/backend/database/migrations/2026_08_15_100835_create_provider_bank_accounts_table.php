<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One current bank account per provider (not a history table — a change
 * overwrites the row; the audit trail lives in `audit_logs`, same as
 * every other sensitive field change in this project). See
 * docs/SETTLEMENT_ARCHITECTURE.md §Bank account security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('provider_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('account_holder_name');
            // Encrypted at rest (see docs/SECURITY.md §Encryption) — cast
            // on the model, not visible as plaintext in a DB dump.
            $table->text('iban');
            $table->string('bank_name');

            // Any change resets this to false — see
            // docs/SETTLEMENT_ARCHITECTURE.md; a settlement can't be
            // marked paid against an unverified account.
            $table->boolean('verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_bank_accounts');
    }
};
