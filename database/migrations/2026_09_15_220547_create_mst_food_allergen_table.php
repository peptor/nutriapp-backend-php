<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Relació aliment <-> al·lergen (0 o molts al·lergens per aliment). confianca ve de l'excel
// curat de Ciqual: ALTA (segur que el conté) o MITJA (per revisar). "NO DETERMINAT" no genera
// cap fila aquí (equival a "no en conté" a efectes de l'app).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mst_food_allergen', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('foodId', 36);
            $table->unsignedBigInteger('allergenId');
            $table->enum('confianca', ['ALTA', 'MITJA']);

            $table->foreign('foodId')->references('id')->on('mst_foods')->onDelete('cascade');
            $table->foreign('allergenId')->references('id')->on('mst_allergens')->onDelete('cascade');
            $table->unique(['foodId', 'allergenId']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_food_allergen');
    }
};
