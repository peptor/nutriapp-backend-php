<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CRM de l'administrador: notes internes sobre un nutricionista (només les veu l'administrador) i control del
// recordatori de caducitat de la llicència (s'envia un sol cop per llicència; vegeu licenses:remind-expiring).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_nutricionista_profiles', function (Blueprint $table) {
            $table->text('adminNotes')->nullable();
        });
        Schema::table('reg_nutricionista_licenses', function (Blueprint $table) {
            $table->timestamp('expiryReminderSentAt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reg_nutricionista_licenses', function (Blueprint $table) {
            $table->dropColumn('expiryReminderSentAt');
        });
        Schema::table('sys_nutricionista_profiles', function (Blueprint $table) {
            $table->dropColumn('adminNotes');
        });
    }
};
