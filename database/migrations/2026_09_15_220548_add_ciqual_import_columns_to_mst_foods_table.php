<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Columnes que calen per importar l'excel curat de Ciqual 2025 (600 aliments) a mst_foods:
// codi extern de Ciqual, subcategoria (només visual), i els nutrients que l'excel dona i que
// encara no teníem (abans només hi havia un vitaminAMcg/vitaminBMcg genèrics). Es mantenen
// els genèrics per compatibilitat amb els aliments existents; els nous s'ompliran amb tots
// dos (el desglossat i el genèric, aquest amb vit_b12 per mantenir el mateix criteri d'abans).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mst_foods', function (Blueprint $table) {
            $table->string('ciqualCode', 20)->nullable()->after('categoryId');
            $table->string('subcategoryId', 36)->nullable()->after('categoryId');

            $table->decimal('sugarsGrams', 6, 2)->nullable()->after('fiberGrams');
            $table->decimal('saltGrams', 6, 2)->nullable()->after('sodiumMg');
            $table->decimal('magnesiumMg', 7, 1)->nullable()->after('calciumMg');
            $table->decimal('phosphorusMg', 7, 1)->nullable()->after('magnesiumMg');
            $table->decimal('potassiumMg', 7, 1)->nullable()->after('phosphorusMg');
            $table->decimal('zincMg', 6, 2)->nullable()->after('potassiumMg');
            $table->decimal('vitaminDMcg', 6, 2)->nullable()->after('vitaminAMcg');
            $table->decimal('vitaminEMg', 6, 2)->nullable()->after('vitaminDMcg');
            $table->decimal('vitaminKMcg', 6, 2)->nullable()->after('vitaminEMg');
            $table->decimal('vitaminCMg', 6, 2)->nullable()->after('vitaminKMcg');
            $table->decimal('vitaminB1Mg', 6, 3)->nullable()->after('vitaminBMcg');
            $table->decimal('vitaminB2Mg', 6, 3)->nullable()->after('vitaminB1Mg');
            $table->decimal('vitaminB3Mg', 6, 3)->nullable()->after('vitaminB2Mg');
            $table->decimal('vitaminB5Mg', 6, 3)->nullable()->after('vitaminB3Mg');
            $table->decimal('vitaminB6Mg', 6, 3)->nullable()->after('vitaminB5Mg');
            $table->decimal('vitaminB9Mcg', 7, 1)->nullable()->after('vitaminB6Mg');
            $table->decimal('vitaminB12Mcg', 6, 2)->nullable()->after('vitaminB9Mcg');

            $table->foreign('subcategoryId')->references('id')->on('mst_subcategory')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('mst_foods', function (Blueprint $table) {
            $table->dropForeign(['subcategoryId']);
            $table->dropColumn([
                'ciqualCode', 'subcategoryId', 'sugarsGrams', 'saltGrams', 'magnesiumMg',
                'phosphorusMg', 'potassiumMg', 'zincMg', 'vitaminDMcg', 'vitaminEMg',
                'vitaminKMcg', 'vitaminCMg', 'vitaminB1Mg', 'vitaminB2Mg', 'vitaminB3Mg',
                'vitaminB5Mg', 'vitaminB6Mg', 'vitaminB9Mcg', 'vitaminB12Mcg',
            ]);
        });
    }
};
