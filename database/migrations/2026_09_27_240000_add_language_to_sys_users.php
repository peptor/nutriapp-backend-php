<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Idioma de l'usuari, guardat perquè el servidor pugui redactar correus, push i alertes en aquest idioma (fins
// ara l'idioma era només del navegador, a `localStorage`, i el backend no en sabia res). Els mateixos 5 idiomes
// seleccionables a la UI (frontend/src/lib/i18n.tsx: LANGUAGES). Vegeu App\Support\Translator.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_users', function (Blueprint $table) {
            $table->enum('language', ['ca', 'es', 'en', 'gl', 'eu'])->default('ca')->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('sys_users', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
