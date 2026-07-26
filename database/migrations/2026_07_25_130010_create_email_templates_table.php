<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// EmailTemplate – editable email templates (Superadmin only). Body/subject contain {{macro}} tokens
// replaced before sending (plain token replacement, no Blade – so user text cannot be abused).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // inquiry_confirmation | instructor_summary | instructor_reminder
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
