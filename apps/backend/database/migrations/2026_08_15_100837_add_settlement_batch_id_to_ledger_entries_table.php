<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a ledger entry as "claimed" by a settlement batch — an entry can
 * only ever be claimed once, which is what prevents the same earnings
 * from being paid out twice. Cleared back to `null` if the claiming
 * batch is later cancelled or fails, so the entry becomes eligible for a
 * future batch again (see
 * App\Domain\Ledger\Actions\AdvanceSettlementStatusAction).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->foreignId('settlement_batch_id')->nullable()->after('provider_id')
                ->constrained()->nullOnDelete();

            $table->index(['provider_id', 'settlement_batch_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settlement_batch_id');
        });
    }
};
