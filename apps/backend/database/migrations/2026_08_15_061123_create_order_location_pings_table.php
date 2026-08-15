<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only breadcrumb trail for an order's active trip window — see
 * docs/LIVE_LOCATION_TRACKING.md. Plain lat/lng, not yet PostGIS (same
 * trade-off as `tow_trucks.current_latitude`/`current_longitude` — see
 * docs/DATABASE_SCHEMA.md §Geography).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_location_pings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('heading')->nullable();
            $table->unsignedSmallInteger('speed_kmh')->nullable();

            // Client-supplied fix time, distinct from `created_at` (server
            // receipt time) — a ping can arrive late over a poor mobile
            // connection.
            $table->timestamp('recorded_at');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_location_pings');
    }
};
