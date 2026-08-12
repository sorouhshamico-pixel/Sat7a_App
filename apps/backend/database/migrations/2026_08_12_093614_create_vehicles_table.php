<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('make');
            $table->string('model');
            $table->unsignedSmallInteger('year');
            // Free-text rather than a fixed enum — vehicle categories vary
            // too widely to enumerate usefully (see spec §19); the
            // frontend can still suggest common values.
            $table->string('type')->nullable();
            $table->string('color')->nullable();
            $table->string('plate_number')->nullable();
            $table->string('notes')->nullable();
            $table->string('image_path')->nullable();

            $table->timestamps();

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
