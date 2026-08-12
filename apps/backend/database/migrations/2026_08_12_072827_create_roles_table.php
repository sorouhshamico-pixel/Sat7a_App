<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Descriptive only ('platform' or 'provider') — see
            // docs/ROLES_PERMISSIONS.md. Not yet enforced at the DB level;
            // provider-scoped role assignment lands with the Provider
            // domain in Phase 3/4.
            $table->string('scope');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
