<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency ledger for inbound gateway webhooks — see
 * docs/PAYMENT_ARCHITECTURE.md §Webhooks. A duplicate delivery of the
 * same (gateway, event_id) is recognized and short-circuited before any
 * payment state changes, no matter how many times the gateway retries.
 * `payload` is stored redacted of sensitive values, same rule as logging
 * (see docs/SECURITY.md §Logging).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();

            $table->string('gateway');
            $table->string('event_id');
            $table->string('event_type');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();

            // Timezone-aware — see docs/DATABASE_SCHEMA.md §Time.
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['gateway', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
