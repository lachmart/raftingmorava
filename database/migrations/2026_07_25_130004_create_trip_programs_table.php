<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TripProgram – program catalog (price list). `name` must exactly match the WP trip page title so the
// inquiry exact-match pairing works. `instructor_reward` is the default reward pre-filled onto the Trip
// and then onto each TripDayAssignment. Null reward = "by agreement".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('category', ['school', 'domestic', 'foreign'])->default('domestic');
            $table->decimal('price_per_person', 10, 2)->nullable();
            $table->decimal('instructor_reward', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_programs');
    }
};
