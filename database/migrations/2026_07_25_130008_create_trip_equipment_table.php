<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Trip ↔ Equipment pivot with a quantity. `price_per_unit` is a snapshot of the rental price at
// selection time (so later price-list changes do not affect planned trips); empty = use current price.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price_per_unit', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['trip_id', 'equipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_equipment');
    }
};
