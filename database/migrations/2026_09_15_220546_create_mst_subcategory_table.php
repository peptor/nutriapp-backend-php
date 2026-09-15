<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Subcategoria d'un aliment (p.ex. "farines" dins de Cereals i tubercles): només es fa
// servir per mostrar un text discret sota el nom de l'aliment, mai per cercar/filtrar (això
// segueix fent-ho la categoria principal, mst_food_categories). El nom es resol via
// mst_translate (entityType='subcategory'); aquí només hi ha l'slug francès d'origen per
// referència/depuració.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mst_subcategory', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('sourceSlugFr')->unique();
            $table->timestamp('createdAt')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_subcategory');
    }
};
