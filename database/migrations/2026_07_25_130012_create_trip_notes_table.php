<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TripNote – the only note mechanism: a comment thread (multiple posts stacked, author + time).
// Belongs either to the whole trip, or optionally to a specific day. Any logged-in user may add one.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('trip_day_id')->nullable()->constrained('trip_days')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_notes');
    }
};
