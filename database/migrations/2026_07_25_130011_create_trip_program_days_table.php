<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TripProgramDay – per-program day-by-day location template. For multi-day routes we know which
// location each day starts from (day 1 = Mohelnice, day 2 = Stodola …). When an inquiry is converted
// to a trip, the generated trip days get their location from this template (matched by order).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_program_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_program_id')->constrained('trip_programs')->cascadeOnDelete();
            $table->unsignedSmallInteger('order')->default(1);
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->timestamps();

            $table->unique(['trip_program_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_program_days');
    }
};
