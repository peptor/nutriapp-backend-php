<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Camps que calen per al nou formulari (més complet) de crear/editar un aliment
// personalitzat: desglossament de greixos i hidrats, minerals traça, estat actiu/inactiu
// i metadades opcionals (origen, observacions, etiquetes).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mst_foods', function (Blueprint $table) {
            $table->decimal('saturatedFatGrams', 6, 2)->nullable()->after('fatGrams');
            $table->decimal('monounsaturatedFatGrams', 6, 2)->nullable()->after('saturatedFatGrams');
            $table->decimal('polyunsaturatedFatGrams', 6, 2)->nullable()->after('monounsaturatedFatGrams');
            $table->decimal('starchGrams', 6, 2)->nullable()->after('sugarsGrams');
            $table->decimal('copperMg', 6, 3)->nullable()->after('zincMg');
            $table->decimal('manganeseMg', 6, 3)->nullable()->after('copperMg');
            $table->decimal('seleniumMcg', 7, 2)->nullable()->after('manganeseMg');
            $table->boolean('actiu')->default(true)->after('nutricionistaId');
            $table->string('origenFont')->nullable()->after('actiu');
            $table->text('observacions')->nullable()->after('origenFont');
            $table->string('etiquetes')->nullable()->after('observacions');
        });
    }

    public function down(): void
    {
        Schema::table('mst_foods', function (Blueprint $table) {
            $table->dropColumn([
                'saturatedFatGrams', 'monounsaturatedFatGrams', 'polyunsaturatedFatGrams',
                'starchGrams', 'copperMg', 'manganeseMg', 'seleniumMcg',
                'actiu', 'origenFont', 'observacions', 'etiquetes',
            ]);
        });
    }
};
