<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per candidate a dispatch wave offered the order to. Never
 * mutated except for `status`/`responded_at` on response — the offer
 * history is the audit trail of who was asked, in what order, and how
 * they responded (see docs/DISPATCH_ENGINE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_offers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tow_truck_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('wave');
            $table->unsignedInteger('distance_meters');
            $table->string('status')->default('pending');

            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['driver_id', 'status']);
        });

        DB::statement(
            "ALTER TABLE dispatch_offers ADD CONSTRAINT dispatch_offers_status_check CHECK (status IN ('pending', 'accepted', 'rejected', 'expired', 'superseded'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_offers');
    }
};
