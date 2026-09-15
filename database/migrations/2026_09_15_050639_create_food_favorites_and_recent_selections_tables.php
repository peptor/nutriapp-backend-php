<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_favorites', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('patientId', 36);
            $table->string('foodId', 36);
            $table->timestamp('createdAt')->useCurrent();

            $table->foreign('patientId')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('foodId')->references('id')->on('foods')->onDelete('cascade');
            $table->unique(['patientId', 'foodId']);
        });

        // Última vegada que el pacient ha afegit cada aliment (a un àpat o als favorits),
        // per poder mostrar la pestanya "Recents" amb els 20 aliments usats més recentment
        // sense haver de rastrejar tot l'historial de food_log a cada consulta.
        Schema::create('food_recent_selections', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('patientId', 36);
            $table->string('foodId', 36);
            $table->timestamp('lastSelectedAt');

            $table->foreign('patientId')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('foodId')->references('id')->on('foods')->onDelete('cascade');
            $table->unique(['patientId', 'foodId']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_recent_selections');
        Schema::dropIfExists('food_favorites');
    }
};
