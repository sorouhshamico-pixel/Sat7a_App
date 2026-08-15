<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors `drivers.rating` (Phase 4) — a cached aggregate, recalculated on
 * every new review rather than computed on every read (see
 * App\Domain\Reviews\Actions\CreateReviewAction and
 * docs/REVIEWS_DISPUTES.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->decimal('rating', 3, 2)->nullable()->after('status');
        });

        DB::statement(
            'ALTER TABLE providers ADD CONSTRAINT providers_rating_range_check CHECK (rating IS NULL OR (rating >= 0 AND rating <= 5))'
        );
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn('rating');
        });
    }
};
