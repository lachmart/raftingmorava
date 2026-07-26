<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Trip – a concrete trip (single- or multi-day). Created manually or by converting an Inquiry.
// Type: "guided" (program + rewards) or "rental" (price from equipment, no instructor).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_program_id')->nullable()->constrained('trip_programs')->nullOnDelete();

            $table->enum('type', ['guided', 'rental'])->default('guided');

            $table->string('name');
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('onsite_phone')->nullable(); // contact at the event location

            $table->date('date_from');
            $table->date('date_to');

            // Planned counts – paying people vs. non-paying escorts (e.g. teachers).
            $table->unsignedInteger('planned_paying_count')->nullable();
            $table->unsignedInteger('planned_escort_count')->nullable();
            $table->string('children_ages')->nullable(); // optional free text

            $table->enum('status', ['planned', 'completed', 'cancelled'])->default('planned');

            // Instructor reward for the trip (guided only), pre-filled from program, full amount per assignment.
            $table->decimal('instructor_reward', 10, 2)->nullable();

            // Client price. For "rental" it is auto-computed from equipment until manually overridden.
            $table->decimal('total_price', 10, 2)->nullable();
            $table->boolean('price_manually_set')->default(false);

            // Link to the inquiry the trip was created from (no DB FK due to circular dependency – handled by Eloquent).
            $table->unsignedBigInteger('inquiry_id')->nullable()->index();

            // --- Settlement (filled after the trip; accounting source data) ---
            $table->unsignedInteger('actual_paying_count')->nullable();
            $table->unsignedInteger('actual_escort_count')->nullable();
            $table->decimal('amount_paid', 10, 2)->nullable();
            $table->enum('payment_method', ['invoice', 'receipt', 'cash'])->nullable();
            $table->string('document_number')->nullable(); // always optional, even for invoices
            $table->date('payment_date')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
