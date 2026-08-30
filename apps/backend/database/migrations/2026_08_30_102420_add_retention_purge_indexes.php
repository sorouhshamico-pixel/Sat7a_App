<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A real Phase 24 (Performance) finding, introduced by Phase 23's own
 * App\Console\Commands\PurgeExpiredDataCommand: its two DELETE queries
 * filter purely by a timestamp column with no other predicate
 * (`otp_codes.expires_at`/`consumed_at`, `order_location_pings.recorded_at`),
 * but neither table had an index usable for that — otp_codes only indexed
 * `(phone, user_type)`, and order_location_pings only indexed
 * `(order_id, recorded_at)`, which a query with no `order_id` predicate
 * can't use as a leading-column lookup. Both would have been full table
 * scans on a command that runs daily forever — exactly the kind of
 * self-inflicted regression a security/hygiene fix can introduce if its
 * own query cost isn't checked. See docs/PERFORMANCE.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->index('expires_at');
            $table->index('consumed_at');
        });

        Schema::table('order_location_pings', function (Blueprint $table) {
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['consumed_at']);
        });

        Schema::table('order_location_pings', function (Blueprint $table) {
            $table->dropIndex(['recorded_at']);
        });
    }
};
