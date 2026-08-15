<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            // Nullable: some audited events (e.g. system/scheduled jobs)
            // have no human actor.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            // Immutable, append-only log (see docs/SECURITY.md §Audit) — no
            // updated_at, entries are never modified after creation.
            // Timezone-aware (not plain `timestamp`) so `useCurrent()`'s
            // DB-side `CURRENT_TIMESTAMP` is stored/read correctly
            // regardless of the Postgres session's timezone setting — see
            // docs/DATABASE_SCHEMA.md §Time.
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
