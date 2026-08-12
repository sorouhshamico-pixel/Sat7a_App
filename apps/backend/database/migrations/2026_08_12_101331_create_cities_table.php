<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->string('slug')->unique();
            $table->string('name');
            $table->string('name_ar');

            // Riyadh is the only launch city — this flag is how later
            // domain logic (dispatch, pricing) avoids hardcoding "Riyadh"
            // and stays ready for expansion instead (see spec §152).
            $table->boolean('is_active')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
