<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pivot trip day ↔ vehicles (a day may have more than one car).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_day_vehicle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_day_id')->constrained('trip_days')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['trip_day_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_day_vehicle');
    }
};
