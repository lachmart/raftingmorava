<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Inquiry – received from WordPress (Contact Form 7 → POST /api/inquiries).
// `program_id` is pre-filled by exact-match pairing of proposed_program_name ↔ TripProgram.name.
// `trip_id` is set when the inquiry is converted to a trip.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->nullable()->constrained('trip_programs')->nullOnDelete();
            $table->string('proposed_program_name')->nullable();
            $table->string('approximate_dates')->nullable();

            $table->string('client_name');
            $table->string('client_email');
            $table->string('client_phone')->nullable();
            $table->unsignedInteger('people_count')->nullable();
            $table->text('note')->nullable();

            $table->enum('status', ['new', 'in_progress', 'confirmed', 'rejected'])->default('new');
            $table->enum('source', ['web', 'manual'])->default('web');

            // Link to the created trip (after conversion). FK to trips – the other direction of the cycle.
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();

            $table->timestamps();

            $table->index(['client_email', 'proposed_program_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
