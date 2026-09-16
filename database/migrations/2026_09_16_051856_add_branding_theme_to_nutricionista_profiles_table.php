<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Preferència visual del nutricionista: 'classic' (font i logos originals) o 'v1' (font i
// logos nous). Es queda a sys_nutricionista_profiles perquè ja és on viuen les preferències
// pròpies d'un nutricionista (dades d'empresa, logo...), sense necessitat de cap taula nova.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_nutricionista_profiles', function (Blueprint $table) {
            $table->enum('brandingTheme', ['classic', 'v1'])->default('classic')->after('logoUrl');
        });
    }

    public function down(): void
    {
        Schema::table('sys_nutricionista_profiles', function (Blueprint $table) {
            $table->dropColumn('brandingTheme');
        });
    }
};
