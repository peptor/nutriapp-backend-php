<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Taula genèrica de traduccions: una fila = tots els noms (per idioma) d'UNA entitat
// (un aliment, una categoria, una subcategoria, un al·lergen...). entityType+entityId
// identifiquen l'entitat (entityId és l'id/uuid propi de la fila a la seva taula d'origen,
// no cal inventar cap "codi" nou). Centralitza el que fins ara es feia amb columnes
// nom_ca/nom_es/... repetides a cada taula (mst_aliments, mst_categories, mst_allergens).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mst_translate', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('entityType', 30);
            $table->string('entityId', 36);
            $table->string('nom_ca')->nullable();
            $table->string('nom_es')->nullable();
            $table->string('nom_eu')->nullable();
            $table->string('nom_gl')->nullable();
            $table->string('nom_pt')->nullable();
            $table->string('nom_it')->nullable();
            $table->string('nom_fr')->nullable();
            $table->string('nom_en')->nullable();

            $table->unique(['entityType', 'entityId']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_translate');
    }
};
