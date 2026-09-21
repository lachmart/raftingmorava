<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Text and map link shown to the client in the trip reminder e-mail (client_reminder_* templates).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->text('email_note')->nullable()->after('name');
            $table->string('map_url')->nullable()->after('email_note');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['email_note', 'map_url']);
        });
    }
};
