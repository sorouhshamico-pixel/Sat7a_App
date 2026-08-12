<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedTinyInteger('current_dispatch_wave')->default(0)->after('status');
            // Flipped true once automated dispatch waves are exhausted with
            // no acceptance — an operations dispatcher must manually assign
            // (see docs/DISPATCH_ENGINE.md §Manual fallback). Purely
            // informational for the ops dashboard; never blocks any action.
            $table->boolean('manual_dispatch_required')->default(false)->after('current_dispatch_wave');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['current_dispatch_wave', 'manual_dispatch_required']);
        });
    }
};
