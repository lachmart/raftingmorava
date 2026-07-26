<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TripDay – one day of a trip. Each day can have its own times, vehicles, driver and instructors
// (via TripDayAssignment). Notes go through TripNote, not a field here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('order')->default(1);

            $table->time('meeting_time')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            // Vehicles are a many-to-many (a day may have more than one) – see trip_day_vehicle.

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_days');
    }
};
