<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The in-app inbox record for every notification event — always created
 * regardless of the recipient's channel preferences (see
 * docs/NOTIFICATIONS.md). `channels` records which external channels
 * (sms/push/email/whatsapp) were actually attempted for this event, based
 * on the recipient's preferences at send time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->json('channels');

            $table->timestampTz('read_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });

        DB::statement(
            "ALTER TABLE notifications ADD CONSTRAINT notifications_type_check CHECK (type IN ('order_created', 'order_status_updated', 'order_cancelled', 'document_expiring', 'document_expired'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
