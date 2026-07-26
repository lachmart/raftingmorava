<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TripDayAssignment – assigns a user to a trip day. One person may have TWO rows on the same day:
// one of type "instructor", one of type "driver". Reward: instructor = full Trip.instructor_reward
// (no splitting), driver = 0, both overridable. Any active user can be assigned.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_day_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_day_id')->constrained('trip_days')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('type', ['instructor', 'driver'])->default('instructor');

            $table->decimal('reward', 10, 2)->nullable();
            $table->boolean('paid')->default(false);
            $table->date('paid_date')->nullable();

            $table->timestamps();

            // The same person should not be assigned twice with the same type on one day.
            $table->unique(['trip_day_id', 'user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_day_assignments');
    }
};
