<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A captured payment can be refunded more than once (partial refunds) —
 * see docs/PAYMENT_ARCHITECTURE.md. Append-only; a failed refund attempt
 * is its own row, never edited into a retry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedInteger('amount');
            $table->string('reason')->nullable();
            $table->string('status')->default('pending');
            $table->string('gateway_refund_id')->nullable();
            $table->string('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['payment_id', 'status']);
        });

        DB::statement(
            "ALTER TABLE refunds ADD CONSTRAINT refunds_status_check CHECK (status IN ('pending', 'succeeded', 'failed'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
