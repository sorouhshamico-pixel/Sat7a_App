<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        DB::statement(
            "ALTER TABLE order_status_history ADD CONSTRAINT order_status_history_to_status_check CHECK (to_status IN ('pending', 'searching_provider', 'provider_assigned', 'provider_en_route', 'provider_arrived', 'vehicle_loading', 'trip_started', 'in_transit', 'vehicle_delivered', 'completed', 'cancelled_by_customer', 'cancelled_by_provider', 'cancelled_by_admin', 'expired', 'disputed', 'refund_pending', 'refunded'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};
