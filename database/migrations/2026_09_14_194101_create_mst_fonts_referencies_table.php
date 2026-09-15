<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bibliografia de Ciqual (sources_2025_11_03.xml): cada "source_code" que apareix a
 * mst_aliment_nutrients.source_code correspon a una citació d'aquí. No forma part de
 * l'esquema original demanat, però l'aporta el mateix dataset i completa la traçabilitat
 * de cada valor nutricional (quina font bibliogràfica el va mesurar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mst_fonts_referencies', function (Blueprint $table) {
            $table->id();
            $table->string('source_code', 50)->unique();
            $table->text('ref_citation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_fonts_referencies');
    }
};
