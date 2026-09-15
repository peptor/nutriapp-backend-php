<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Biblioteca nutricional detallada (Ciqual 2025), en taules pròpies prefixades amb
 * "mst_" perquè són catàleg mestre, no dades pròpies de cap nutricionista. Adaptat de
 * l'esquema/import proporcionat per l'usuari: mateixos noms de taula i columna (en
 * castellà/català planer, claus enteres autoincrementals) perquè l'importador de Ciqual
 * ja escrit el pugui fer servir sense reescriure's, només amb el prefix "mst_" afegit.
 *
 * Diferències respecte a la resta de l'app: aquí les claus primàries són enters
 * autoincrementals (no UUID) perquè l'importador fa servir LAST_INSERT_ID() per
 * l'upsert de categories/nutrients/aliments; "nutricionista_id" i "pacient_id" sí que
 * són UUID (string(36)) perquè referencien els usuaris/pacients reals de l'aplicació.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mst_fonts_dades', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->string('organisme', 200)->nullable();
            $table->string('versio', 50)->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('llicencia', 500)->nullable();
            $table->string('identificador_extern')->nullable();
            $table->dateTime('data_importacio')->nullable();
            $table->boolean('activa')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->unique(['nom', 'versio'], 'uk_font_identificador');
        });

        Schema::create('mst_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('mst_categories')->nullOnDelete();
            $table->string('codi_extern', 50)->nullable();
            $table->unsignedTinyInteger('nivell')->default(1);
            $table->string('nom');
            $table->string('nom_original')->nullable();
            $table->integer('ordre')->default(0);
            $table->boolean('activa')->default(true);
            $table->unique(['parent_id', 'nom'], 'uk_category_parent_nom');
            $table->unique(['codi_extern', 'nivell'], 'uk_category_source_code');
            $table->index('nom', 'idx_categories_nom');
        });

        Schema::create('mst_nutrients', function (Blueprint $table) {
            $table->id();
            $table->string('codi', 80)->unique('uk_nutrients_codi');
            $table->string('nom', 150);
            $table->string('nom_original_fr')->nullable();
            $table->enum('grup', ['energia', 'macronutrient', 'micronutrient', 'vitamina', 'mineral', 'lipid', 'altres'])->default('altres');
            $table->string('unitat', 20);
            $table->string('infoods_code', 100)->nullable();
            $table->string('font_code', 50)->nullable()->unique('uk_nutrients_font_code');
            $table->integer('ordre')->default(0);
            $table->boolean('actiu')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->index('grup', 'idx_nutrients_grup');
            $table->index('infoods_code', 'idx_nutrients_infoods');
        });

        Schema::create('mst_aliments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('font_id')->nullable()->constrained('mst_fonts_dades')->nullOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('mst_categories')->nullOnDelete();
            $table->string('external_id', 100)->nullable();
            $table->string('nom');
            $table->string('nom_original_fr', 500)->nullable();
            $table->string('nom_eng', 500)->nullable();
            // Traduccions als idiomes de l'app (frontend/src/lib/i18n.tsx): es completen
            // en un pas posterior (traducció manual o assistida), no les dona Ciqual.
            $table->string('nom_ca', 500)->nullable();
            $table->string('nom_es', 500)->nullable();
            $table->string('nom_eu', 500)->nullable();
            $table->string('nom_gl', 500)->nullable();
            $table->string('nom_pt', 500)->nullable();
            $table->string('nom_it', 500)->nullable();
            $table->string('nom_cientific', 500)->nullable();
            $table->text('descripcio')->nullable();
            // Foto de l'aliment: quan coincideix amb un dels 97 aliments de la biblioteca
            // pròpia (taula "foods") es reutilitza la mateixa icona en lloc de duplicar-la.
            $table->string('imatge_url', 500)->nullable();
            $table->string('marca', 150)->nullable();
            $table->decimal('factor_jones', 10, 4)->nullable();
            $table->decimal('pes_edible', 10, 2)->nullable();
            $table->enum('unitat_base', ['g', 'ml'])->default('g');
            $table->boolean('es_personalitzat')->default(false);
            $table->string('nutricionista_id', 36)->nullable();
            $table->boolean('actiu')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->foreign('nutricionista_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['font_id', 'external_id'], 'uk_aliment_font_external');
            $table->index('nom', 'idx_aliments_nom');
            $table->index([DB::raw('nom_original_fr(191)')], 'idx_aliments_nom_original');
            $table->index('categoria_id', 'idx_aliments_categoria');
            $table->index('external_id', 'idx_aliments_external');
        });

        Schema::create('mst_aliment_nutrients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aliment_id')->constrained('mst_aliments')->cascadeOnDelete();
            $table->foreignId('nutrient_id')->constrained('mst_nutrients')->cascadeOnDelete();
            $table->decimal('valor', 18, 8)->nullable();
            $table->string('valor_original', 100)->nullable();
            $table->enum('qualificacio', ['valor', 'menys_que', 'traces', 'sense_dada'])->default('valor');
            $table->decimal('valor_min', 18, 8)->nullable();
            $table->decimal('valor_max', 18, 8)->nullable();
            $table->char('codi_confianca', 1)->nullable();
            $table->foreignId('font_id')->nullable()->constrained('mst_fonts_dades')->nullOnDelete();
            $table->string('source_code', 50)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['aliment_id', 'nutrient_id'], 'uk_aliment_nutrient');
            $table->index('aliment_id', 'idx_an_aliment');
            $table->index('nutrient_id', 'idx_an_nutrient');
        });

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

        Schema::create('mst_allergens', function (Blueprint $table) {
            $table->id();
            $table->string('codi', 50)->nullable()->unique('uk_allergen_codi');
            $table->string('nom', 100)->unique('uk_allergen_nom');
            $table->integer('ordre')->default(0);
            $table->boolean('actiu')->default(true);
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
            $table->foreign('nutricionista_id')->references('id')->on('users')->nullOnDelete();
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

        Schema::create('mst_tipus_apats', function (Blueprint $table) {
            $table->id();
            $table->string('codi', 50)->unique('uk_tipus_apats_codi');
            $table->string('nom', 100)->unique('uk_tipus_apats_nom');
            $table->integer('ordre')->default(0);
            $table->boolean('actiu')->default(true);
        });

        Schema::create('mst_registres_alimentacio', function (Blueprint $table) {
            $table->id();
            $table->string('pacient_id', 36);
            $table->date('data');
            $table->text('observacions')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->foreign('pacient_id')->references('id')->on('patients')->cascadeOnDelete();
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

        $now = now();

        DB::table('mst_fonts_dades')->insert([
            'nom' => 'Ciqual',
            'organisme' => 'Anses - Observatoire des aliments',
            'versio' => '2025',
            'url' => 'https://ciqual.anses.fr/',
            'llicencia' => 'Etalab Open License 2.0',
            'identificador_extern' => 'DOI:10.57745/RDMHWY',
            'data_importacio' => null,
            'created_at' => $now,
        ]);

        DB::table('mst_nutrients')->insert([
            ['codi' => 'ENERGY_KCAL', 'nom' => 'Energia', 'nom_original_fr' => 'Énergie', 'grup' => 'energia', 'unitat' => 'kcal', 'infoods_code' => 'ENERC_KCAL', 'ordre' => 10, 'created_at' => $now],
            ['codi' => 'ENERGY_KJ', 'nom' => 'Energia', 'nom_original_fr' => 'Énergie', 'grup' => 'energia', 'unitat' => 'kJ', 'infoods_code' => 'ENERC_KJ', 'ordre' => 11, 'created_at' => $now],
            ['codi' => 'WATER', 'nom' => 'Aigua', 'nom_original_fr' => 'Eau', 'grup' => 'altres', 'unitat' => 'g', 'infoods_code' => 'WATER', 'ordre' => 20, 'created_at' => $now],
            ['codi' => 'PROTEIN', 'nom' => 'Proteïnes', 'nom_original_fr' => 'Protéines', 'grup' => 'macronutrient', 'unitat' => 'g', 'infoods_code' => 'PROCNT', 'ordre' => 30, 'created_at' => $now],
            ['codi' => 'CARBS', 'nom' => 'Hidrats de carboni', 'nom_original_fr' => 'Glucides', 'grup' => 'macronutrient', 'unitat' => 'g', 'infoods_code' => 'CHOAVL', 'ordre' => 31, 'created_at' => $now],
            ['codi' => 'FAT', 'nom' => 'Greixos', 'nom_original_fr' => 'Lipides', 'grup' => 'macronutrient', 'unitat' => 'g', 'infoods_code' => 'FAT', 'ordre' => 32, 'created_at' => $now],
            ['codi' => 'FIBER', 'nom' => 'Fibra alimentària', 'nom_original_fr' => 'Fibres alimentaires', 'grup' => 'macronutrient', 'unitat' => 'g', 'infoods_code' => 'FIBTG', 'ordre' => 33, 'created_at' => $now],
            ['codi' => 'SUGARS', 'nom' => 'Sucres', 'nom_original_fr' => 'Sucres', 'grup' => 'macronutrient', 'unitat' => 'g', 'infoods_code' => 'SUGAR', 'ordre' => 34, 'created_at' => $now],
            ['codi' => 'STARCH', 'nom' => 'Midó', 'nom_original_fr' => 'Amidon', 'grup' => 'macronutrient', 'unitat' => 'g', 'infoods_code' => 'STARCH', 'ordre' => 35, 'created_at' => $now],
            ['codi' => 'ALCOHOL', 'nom' => 'Alcohol', 'nom_original_fr' => 'Alcool', 'grup' => 'altres', 'unitat' => 'g', 'infoods_code' => 'ALC', 'ordre' => 36, 'created_at' => $now],
            ['codi' => 'SODIUM', 'nom' => 'Sodi', 'nom_original_fr' => 'Sodium', 'grup' => 'mineral', 'unitat' => 'mg', 'infoods_code' => 'NA', 'ordre' => 50, 'created_at' => $now],
            ['codi' => 'POTASSIUM', 'nom' => 'Potassi', 'nom_original_fr' => 'Potassium', 'grup' => 'mineral', 'unitat' => 'mg', 'infoods_code' => 'K', 'ordre' => 51, 'created_at' => $now],
            ['codi' => 'CALCIUM', 'nom' => 'Calci', 'nom_original_fr' => 'Calcium', 'grup' => 'mineral', 'unitat' => 'mg', 'infoods_code' => 'CA', 'ordre' => 52, 'created_at' => $now],
            ['codi' => 'MAGNESIUM', 'nom' => 'Magnesi', 'nom_original_fr' => 'Magnésium', 'grup' => 'mineral', 'unitat' => 'mg', 'infoods_code' => 'MG', 'ordre' => 53, 'created_at' => $now],
            ['codi' => 'PHOSPHORUS', 'nom' => 'Fòsfor', 'nom_original_fr' => 'Phosphore', 'grup' => 'mineral', 'unitat' => 'mg', 'infoods_code' => 'P', 'ordre' => 54, 'created_at' => $now],
            ['codi' => 'IRON', 'nom' => 'Ferro', 'nom_original_fr' => 'Fer', 'grup' => 'mineral', 'unitat' => 'mg', 'infoods_code' => 'FE', 'ordre' => 55, 'created_at' => $now],
            ['codi' => 'ZINC', 'nom' => 'Zinc', 'nom_original_fr' => 'Zinc', 'grup' => 'mineral', 'unitat' => 'mg', 'infoods_code' => 'ZN', 'ordre' => 56, 'created_at' => $now],
            ['codi' => 'VIT_A', 'nom' => 'Vitamina A', 'nom_original_fr' => 'Vitamine A', 'grup' => 'vitamina', 'unitat' => 'µg', 'infoods_code' => 'VITA', 'ordre' => 70, 'created_at' => $now],
            ['codi' => 'RETINOL', 'nom' => 'Retinol', 'nom_original_fr' => 'Rétinol', 'grup' => 'vitamina', 'unitat' => 'µg', 'infoods_code' => 'RETOL', 'ordre' => 71, 'created_at' => $now],
            ['codi' => 'BETA_CAROTENE', 'nom' => 'Beta-carotè', 'nom_original_fr' => 'Bêta-carotène', 'grup' => 'vitamina', 'unitat' => 'µg', 'infoods_code' => 'CARTB', 'ordre' => 72, 'created_at' => $now],
            ['codi' => 'VIT_B1', 'nom' => 'Vitamina B1', 'nom_original_fr' => 'Vitamine B1', 'grup' => 'vitamina', 'unitat' => 'mg', 'infoods_code' => 'THIA', 'ordre' => 80, 'created_at' => $now],
            ['codi' => 'VIT_B2', 'nom' => 'Vitamina B2', 'nom_original_fr' => 'Vitamine B2', 'grup' => 'vitamina', 'unitat' => 'mg', 'infoods_code' => 'RIBF', 'ordre' => 81, 'created_at' => $now],
            ['codi' => 'VIT_B3', 'nom' => 'Vitamina B3', 'nom_original_fr' => 'Vitamine B3', 'grup' => 'vitamina', 'unitat' => 'mg', 'infoods_code' => 'NIA', 'ordre' => 82, 'created_at' => $now],
            ['codi' => 'VIT_B6', 'nom' => 'Vitamina B6', 'nom_original_fr' => 'Vitamine B6', 'grup' => 'vitamina', 'unitat' => 'mg', 'infoods_code' => 'VITB6', 'ordre' => 83, 'created_at' => $now],
            ['codi' => 'VIT_B9', 'nom' => 'Vitamina B9 / Folat', 'nom_original_fr' => 'Vitamine B9 / Folates', 'grup' => 'vitamina', 'unitat' => 'µg', 'infoods_code' => 'FOL', 'ordre' => 84, 'created_at' => $now],
            ['codi' => 'VIT_B12', 'nom' => 'Vitamina B12', 'nom_original_fr' => 'Vitamine B12', 'grup' => 'vitamina', 'unitat' => 'µg', 'infoods_code' => 'VITB12', 'ordre' => 85, 'created_at' => $now],
            ['codi' => 'VIT_C', 'nom' => 'Vitamina C', 'nom_original_fr' => 'Vitamine C', 'grup' => 'vitamina', 'unitat' => 'mg', 'infoods_code' => 'VITC', 'ordre' => 86, 'created_at' => $now],
            ['codi' => 'VIT_D', 'nom' => 'Vitamina D', 'nom_original_fr' => 'Vitamine D', 'grup' => 'vitamina', 'unitat' => 'µg', 'infoods_code' => 'VITD', 'ordre' => 87, 'created_at' => $now],
            ['codi' => 'VIT_E', 'nom' => 'Vitamina E', 'nom_original_fr' => 'Vitamine E', 'grup' => 'vitamina', 'unitat' => 'mg', 'infoods_code' => 'TOCPHA', 'ordre' => 88, 'created_at' => $now],
            ['codi' => 'VIT_K', 'nom' => 'Vitamina K', 'nom_original_fr' => 'Vitamine K', 'grup' => 'vitamina', 'unitat' => 'µg', 'infoods_code' => 'VITK', 'ordre' => 89, 'created_at' => $now],
            ['codi' => 'CHOLESTEROL', 'nom' => 'Colesterol', 'nom_original_fr' => 'Cholestérol', 'grup' => 'lipid', 'unitat' => 'mg', 'infoods_code' => 'CHOLE', 'ordre' => 100, 'created_at' => $now],
        ]);

        DB::table('mst_allergens')->insert([
            ['codi' => 'GLUTEN', 'nom' => 'Gluten', 'ordre' => 1],
            ['codi' => 'CRUSTACEANS', 'nom' => 'Crustacis', 'ordre' => 2],
            ['codi' => 'EGGS', 'nom' => 'Ous', 'ordre' => 3],
            ['codi' => 'FISH', 'nom' => 'Peix', 'ordre' => 4],
            ['codi' => 'PEANUTS', 'nom' => 'Cacauet', 'ordre' => 5],
            ['codi' => 'SOY', 'nom' => 'Soja', 'ordre' => 6],
            ['codi' => 'MILK', 'nom' => 'Llet', 'ordre' => 7],
            ['codi' => 'NUTS', 'nom' => 'Fruits de closca', 'ordre' => 8],
            ['codi' => 'CELERY', 'nom' => 'Api', 'ordre' => 9],
            ['codi' => 'MUSTARD', 'nom' => 'Mostassa', 'ordre' => 10],
            ['codi' => 'SESAME', 'nom' => 'Sèsam', 'ordre' => 11],
            ['codi' => 'SULPHITES', 'nom' => 'Sulfits', 'ordre' => 12],
            ['codi' => 'LUPIN', 'nom' => 'Tramús', 'ordre' => 13],
            ['codi' => 'MOLLUSCS', 'nom' => 'Mol·luscs', 'ordre' => 14],
        ]);

        DB::table('mst_tipus_apats')->insert([
            ['codi' => 'ESMORZAR', 'nom' => 'Esmorzar', 'ordre' => 10],
            ['codi' => 'MIG_MATI', 'nom' => 'Mig matí', 'ordre' => 20],
            ['codi' => 'DINAR', 'nom' => 'Dinar', 'ordre' => 30],
            ['codi' => 'BERENAR', 'nom' => 'Berenar', 'ordre' => 40],
            ['codi' => 'SOPAR', 'nom' => 'Sopar', 'ordre' => 50],
            ['codi' => 'RECENA', 'nom' => 'Recena', 'ordre' => 60],
            ['codi' => 'ALTRES', 'nom' => 'Altres', 'ordre' => 70],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_registre_aliments');
        Schema::dropIfExists('mst_registres_alimentacio');
        Schema::dropIfExists('mst_recepta_ingredients');
        Schema::dropIfExists('mst_receptes');
        Schema::dropIfExists('mst_aliment_allergens');
        Schema::dropIfExists('mst_allergens');
        Schema::dropIfExists('mst_racions');
        Schema::dropIfExists('mst_aliments_sinonims');
        Schema::dropIfExists('mst_aliment_nutrients');
        Schema::dropIfExists('mst_aliments');
        Schema::dropIfExists('mst_nutrients');
        Schema::dropIfExists('mst_categories');
        Schema::dropIfExists('mst_tipus_apats');
        Schema::dropIfExists('mst_fonts_dades');
    }
};
