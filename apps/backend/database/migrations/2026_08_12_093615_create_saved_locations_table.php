<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_locations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('label');
            // Only meaningful when label = 'custom' — see
            // App\Domain\Customers\Enums\SavedLocationLabel.
            $table->string('custom_label')->nullable();

            // Plain lat/lng for now — see docs/DATABASE_SCHEMA.md
            // §Geography; converted once PostGIS lands in Phase 6. No
            // location *history* is kept here, only the current saved
            // point per label (see docs/SECURITY.md §Data retention).
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('formatted_address');
            $table->string('place_id')->nullable();
            $table->string('notes')->nullable();

            $table->timestamps();

            $table->index('customer_id');
        });

        DB::statement(
            "ALTER TABLE saved_locations ADD CONSTRAINT saved_locations_label_check CHECK (label IN ('home', 'work', 'custom'))"
        );

        // A customer can only have one "home" and one "work" saved
        // location — custom ones are unlimited (partial unique index).
        DB::statement(
            "CREATE UNIQUE INDEX saved_locations_one_home_or_work_per_customer ON saved_locations (customer_id, label) WHERE label IN ('home', 'work')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_locations');
    }
};
