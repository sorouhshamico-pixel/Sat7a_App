<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * See docs/SETTLEMENT_ARCHITECTURE.md §Settlement batches. `net` may be
 * negative (a provider who collected mostly cash can owe the platform
 * more than the platform owes them — see docs/PAYMENT_ARCHITECTURE.md);
 * `gross`/`commission`/`deductions` are informational sums of already
 * non-negative ledger amounts, so those stay unsigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_batches', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('provider_id')->constrained()->restrictOnDelete();

            $table->date('period_start');
            $table->date('period_end');

            $table->unsignedInteger('gross')->default(0);
            $table->unsignedInteger('commission')->default(0);
            $table->unsignedInteger('deductions')->default(0);
            $table->integer('net');

            $table->string('status')->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('reference')->nullable();
            $table->string('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['provider_id', 'status']);
        });

        DB::statement(
            "ALTER TABLE settlement_batches ADD CONSTRAINT settlement_batches_status_check CHECK (status IN ('draft', 'pending_approval', 'approved', 'processing', 'paid', 'failed', 'cancelled'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_batches');
    }
};
