<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Výstupní místo" – where the day's section ends, so Planning can show e.g. "Postřelmov → Vila Háj".
// Added to both the trip day and the program's day template (copied over when days are generated).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_days', function (Blueprint $table) {
            $table->foreignId('end_location_id')->nullable()->after('location_id')
                ->constrained('locations')->nullOnDelete();
        });

        Schema::table('trip_program_days', function (Blueprint $table) {
            $table->foreignId('end_location_id')->nullable()->after('location_id')
                ->constrained('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trip_days', function (Blueprint $table) {
            $table->dropConstrainedForeignId('end_location_id');
        });

        Schema::table('trip_program_days', function (Blueprint $table) {
            $table->dropConstrainedForeignId('end_location_id');
        });
    }
};
