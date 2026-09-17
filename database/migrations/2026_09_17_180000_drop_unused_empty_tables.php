<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Elimina 8 taules buides que van quedar de l'scaffolding de la importació Ciqual
// (receptes, sinònims, racions i un "diari d'aliments" alternatiu que mai es va connectar
// a cap controlador ni ruta — el diari real de l'app fa servir reg_daily_records) i la
// taula "alerts" original (model Alert.php sense cap ús enlloc del codi). Verificat abans
// d'esborrar: 0 files a totes, i cap model/controlador/ruta les referencia.
return new class extends Migration
{
    public function up(): void
    {
        // Ordre invers al de creació perquè les FK ho permetin (fills abans que pares).
        Schema::dropIfExists('mst_registre_aliments');
        Schema::dropIfExists('mst_registres_alimentacio');
        Schema::dropIfExists('mst_recepta_ingredients');
        Schema::dropIfExists('mst_receptes');
        Schema::dropIfExists('mst_aliment_allergens');
        Schema::dropIfExists('mst_racions');
        Schema::dropIfExists('mst_aliments_sinonims');
        Schema::dropIfExists('reg_alerts');
    }

    public function down(): void
    {
        Schema::create('mst_aliments_sinonims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aliment_id')->constrained('mst_aliments')->cascadeOnDelete();
            $table->string('nom');
            $table->unique(['aliment_id', 'nom'], 'uk_sinonim_aliment_nom');
            $table->index('nom', 'idx_sinonim_nom');
        });

        Schema::create('mst_racions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aliment_id')->constrained('mst_aliments')->cascadeOnDelete();
            $table->string('nom', 150);
            $table->decimal('grams', 10, 2);
            $table->decimal('quantitat', 10, 2)->default(1);
            $table->string('unitat', 50)->nullable();
            $table->boolean('es_predeterminada')->default(false);
            $table->index('aliment_id', 'idx_racions_aliment');
        });

        Schema::create('mst_aliment_allergens', function (Blueprint $table) {
            $table->foreignId('aliment_id')->constrained('mst_aliments')->cascadeOnDelete();
            $table->foreignId('allergen_id')->constrained('mst_allergens')->cascadeOnDelete();
            $table->primary(['aliment_id', 'allergen_id']);
        });

        Schema::create('mst_receptes', function (Blueprint $table) {
            $table->id();
            $table->string('nutricionista_id', 36)->nullable();
            $table->string('nom');
            $table->text('descripcio')->nullable();
            $table->decimal('racions', 10, 2)->default(1);
            $table->integer('temps_preparacio')->nullable();
            $table->text('instruccions')->nullable();
            $table->boolean('es_publica')->default(false);
            $table->boolean('activa')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            // 'users' es deia així quan es va crear aquesta taula (2026-09-14); des de la
            // migració de prefixos mst_/reg_/sys_ (2026-09-15) és 'sys_users'.
            $table->foreign('nutricionista_id')->references('id')->on('sys_users')->nullOnDelete();
            $table->index('nutricionista_id', 'idx_receptes_nutricionista');
        });

        Schema::create('mst_recepta_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recepta_id')->constrained('mst_receptes')->cascadeOnDelete();
            $table->foreignId('aliment_id')->constrained('mst_aliments')->restrictOnDelete();
            $table->decimal('quantitat', 10, 2);
            $table->string('unitat', 50)->default('g');
            $table->decimal('grams', 10, 2);
            $table->integer('ordre')->default(0);
            $table->index('recepta_id', 'idx_ri_recepta');
            $table->index('aliment_id', 'idx_ri_aliment');
        });

        Schema::create('mst_registres_alimentacio', function (Blueprint $table) {
            $table->id();
            $table->string('pacient_id', 36);
            $table->date('data');
            $table->text('observacions')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            // 'patients' es deia així quan es va crear aquesta taula; ara és 'sys_patients'.
            $table->foreign('pacient_id')->references('id')->on('sys_patients')->cascadeOnDelete();
            $table->unique(['pacient_id', 'data'], 'uk_pacient_data');
            $table->index('pacient_id', 'idx_registre_pacient');
            $table->index('data', 'idx_registre_data');
        });

        Schema::create('mst_registre_aliments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registre_id')->constrained('mst_registres_alimentacio')->cascadeOnDelete();
            $table->foreignId('tipus_apat_id')->constrained('mst_tipus_apats')->restrictOnDelete();
            $table->foreignId('aliment_id')->nullable()->constrained('mst_aliments')->restrictOnDelete();
            $table->foreignId('recepta_id')->nullable()->constrained('mst_receptes')->restrictOnDelete();
            $table->decimal('quantitat', 10, 2);
            $table->decimal('grams', 10, 2);
            $table->string('observacions', 500)->nullable();
            $table->integer('ordre')->default(0);
            $table->index('registre_id', 'idx_ra_registre');
            $table->index('tipus_apat_id', 'idx_ra_apat');
            $table->index('aliment_id', 'idx_ra_aliment');
        });

        Schema::create('reg_alerts', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('patientId', 36);
            $table->string('assignmentId', 36)->nullable();
            $table->string('type');
            $table->text('message');
            $table->string('severity')->default('info');
            $table->boolean('isRead')->default(false);
            $table->timestamp('createdAt')->useCurrent();
        });
    }
};
