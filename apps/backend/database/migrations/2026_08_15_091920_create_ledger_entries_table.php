<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only, immutable financial ledger — see
 * docs/SETTLEMENT_ARCHITECTURE.md. A provider's balance is never just
 * `sum(completed orders)`; it's derived by summing these entries (see
 * App\Domain\Ledger\Actions\GetProviderBalanceAction). Historical entries
 * are never edited or deleted — a correction is a new `adjustment` entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained()->restrictOnDelete();

            $table->string('type');
            $table->string('direction');
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('SAR');
            $table->string('description')->nullable();

            // Timezone-aware (not plain `timestamp`) — critical here since
            // provider balance is split into pending/available buckets by
            // comparing `created_at` against a computed real-time cutoff
            // (see App\Domain\Ledger\Actions\GetProviderBalanceAction); a
            // non-tz column silently corrupted this comparison by the
            // Postgres session's UTC offset. See docs/DATABASE_SCHEMA.md
            // §Time.
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['provider_id', 'type', 'created_at']);
            $table->index(['payment_id', 'type']);
        });

        DB::statement(
            "ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_type_check CHECK (type IN ('customer_payment', 'platform_commission', 'gateway_fee', 'provider_payable', 'refund', 'adjustment', 'settlement'))"
        );
        DB::statement(
            "ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_direction_check CHECK (direction IN ('credit', 'debit'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
