<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-user table layout (column order/visibility, rows per page). Filament keeps these only in the
// session, so they were lost on every logout – this stores them for the next login.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_preferences');
    }
};
