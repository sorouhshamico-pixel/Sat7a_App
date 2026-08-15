<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * See docs/PAYMENT_ARCHITECTURE.md. An order may have more than one
 * payment row over its life (a failed card attempt followed by a retry),
 * so `order_id` is intentionally not unique — business logic (see
 * App\Domain\Payments\Actions\CreatePaymentAction) prevents a second
 * concurrently-active payment for the same order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->string('gateway');
            $table->string('gateway_payment_id')->nullable()->index();
            $table->string('method');

            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('SAR');

            $table->string('status')->default('pending');

            // Safe metadata only — never PAN/CVV, see docs/PAYMENT_ARCHITECTURE.md §Card data.
            $table->string('card_brand')->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('failure_reason')->nullable();

            // Lets a client retry a create-payment call safely — see
            // docs/PAYMENT_ARCHITECTURE.md §Idempotency.
            $table->string('idempotency_key')->nullable()->unique();

            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        DB::statement(
            "ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('pending', 'authorized', 'captured', 'failed', 'cancelled', 'partially_refunded', 'refunded'))"
        );
        DB::statement(
            "ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('mada', 'visa', 'mastercard', 'apple_pay', 'cash'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
