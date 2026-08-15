<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * See docs/REVIEWS_DISPUTES.md. Not unique on `order_id` — a resolved or
 * rejected dispute is kept as history, and a customer can raise a new one
 * later — but App\Domain\Disputes\Actions\RaiseDisputeAction enforces at
 * most one non-terminal (`open`/`under_review`) dispute per order at the
 * application layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->string('reason');
            $table->text('description');
            $table->string('status')->default('open');

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('status');
        });

        DB::statement(
            "ALTER TABLE disputes ADD CONSTRAINT disputes_reason_check CHECK (reason IN ('overcharge', 'service_quality', 'damage', 'no_show', 'other'))"
        );
        DB::statement(
            "ALTER TABLE disputes ADD CONSTRAINT disputes_status_check CHECK (status IN ('open', 'under_review', 'resolved', 'rejected'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
