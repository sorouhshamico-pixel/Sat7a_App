<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One review per completed order (`order_id` unique) — see
 * docs/REVIEWS_DISPUTES.md. Denormalizes `provider_id`/`driver_id` off the
 * order at write time rather than joining through it on every read, since
 * a provider's/driver's aggregate rating is recalculated from this table
 * on every insert (see App\Domain\Reviews\Actions\CreateReviewAction).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->index(['provider_id', 'created_at']);
            $table->index(['driver_id', 'created_at']);
        });

        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_range_check CHECK (rating >= 1 AND rating <= 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
