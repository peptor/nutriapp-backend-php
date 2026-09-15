<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// La taula mst_allergens ja existia (creada durant la integració de Ciqual) amb els 14
// al·lèrgens de declaració obligatòria a la UE i el nom en català. Aquí hi afegim el nom en
// la resta d'idiomes de l'app (es, eu, gl, pt, it, fr, en) — terminologia oficial
// d'etiquetatge alimentari en cada idioma, no traducció automàtica — i ajustem dos noms
// catalans (Cacauet->Cacauets, Tramús->Tramussos) per quedar en plural, com a la resta.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mst_allergens', function (Blueprint $table) {
            $table->string('nom_es')->nullable()->after('nom');
            $table->string('nom_eu')->nullable()->after('nom_es');
            $table->string('nom_gl')->nullable()->after('nom_eu');
            $table->string('nom_pt')->nullable()->after('nom_gl');
            $table->string('nom_it')->nullable()->after('nom_pt');
            $table->string('nom_fr')->nullable()->after('nom_it');
            $table->string('nom_en')->nullable()->after('nom_fr');
        });

        $rows = [
            'GLUTEN' => ['ca' => 'Gluten', 'es' => 'Gluten', 'eu' => 'Glutena', 'gl' => 'Glute', 'pt' => 'Glúten', 'it' => 'Glutine', 'fr' => 'Gluten', 'en' => 'Gluten'],
            'CRUSTACEANS' => ['ca' => 'Crustacis', 'es' => 'Crustáceos', 'eu' => 'Krustazeoak', 'gl' => 'Crustáceos', 'pt' => 'Crustáceos', 'it' => 'Crostacei', 'fr' => 'Crustacés', 'en' => 'Crustaceans'],
            'EGGS' => ['ca' => 'Ous', 'es' => 'Huevos', 'eu' => 'Arrautzak', 'gl' => 'Ovos', 'pt' => 'Ovos', 'it' => 'Uova', 'fr' => 'Œufs', 'en' => 'Eggs'],
            'FISH' => ['ca' => 'Peix', 'es' => 'Pescado', 'eu' => 'Arraina', 'gl' => 'Peixe', 'pt' => 'Peixe', 'it' => 'Pesce', 'fr' => 'Poisson', 'en' => 'Fish'],
            'PEANUTS' => ['ca' => 'Cacauets', 'es' => 'Cacahuetes', 'eu' => 'Kakahueteak', 'gl' => 'Cacahuetes', 'pt' => 'Amendoins', 'it' => 'Arachidi', 'fr' => 'Arachides', 'en' => 'Peanuts'],
            'SOY' => ['ca' => 'Soja', 'es' => 'Soja', 'eu' => 'Soja', 'gl' => 'Soia', 'pt' => 'Soja', 'it' => 'Soia', 'fr' => 'Soja', 'en' => 'Soybeans'],
            'MILK' => ['ca' => 'Llet', 'es' => 'Leche', 'eu' => 'Esnea', 'gl' => 'Leite', 'pt' => 'Leite', 'it' => 'Latte', 'fr' => 'Lait', 'en' => 'Milk'],
            'NUTS' => ['ca' => 'Fruits de closca', 'es' => 'Frutos de cáscara', 'eu' => 'Fruitu lehorrak', 'gl' => 'Froitos secos', 'pt' => 'Frutos de casca rija', 'it' => 'Frutta a guscio', 'fr' => 'Fruits à coque', 'en' => 'Tree nuts'],
            'CELERY' => ['ca' => 'Api', 'es' => 'Apio', 'eu' => 'Apioa', 'gl' => 'Apio', 'pt' => 'Aipo', 'it' => 'Sedano', 'fr' => 'Céleri', 'en' => 'Celery'],
            'MUSTARD' => ['ca' => 'Mostassa', 'es' => 'Mostaza', 'eu' => 'Mostaza', 'gl' => 'Mostaza', 'pt' => 'Mostarda', 'it' => 'Senape', 'fr' => 'Moutarde', 'en' => 'Mustard'],
            'SESAME' => ['ca' => 'Sèsam', 'es' => 'Sésamo', 'eu' => 'Sesamoa', 'gl' => 'Sésamo', 'pt' => 'Sésamo', 'it' => 'Sesamo', 'fr' => 'Sésame', 'en' => 'Sesame'],
            'SULPHITES' => ['ca' => 'Sulfits', 'es' => 'Sulfitos', 'eu' => 'Sulfitoak', 'gl' => 'Sulfitos', 'pt' => 'Sulfitos', 'it' => 'Solfiti', 'fr' => 'Sulfites', 'en' => 'Sulphites'],
            'LUPIN' => ['ca' => 'Tramussos', 'es' => 'Altramuces', 'eu' => 'Altramuzak', 'gl' => 'Chochos', 'pt' => 'Tremoços', 'it' => 'Lupini', 'fr' => 'Lupin', 'en' => 'Lupin'],
            'MOLLUSCS' => ['ca' => 'Mol·luscs', 'es' => 'Moluscos', 'eu' => 'Moluskuak', 'gl' => 'Moluscos', 'pt' => 'Moluscos', 'it' => 'Molluschi', 'fr' => 'Mollusques', 'en' => 'Molluscs'],
        ];

        foreach ($rows as $codi => $noms) {
            DB::table('mst_allergens')->where('codi', $codi)->update([
                'nom' => $noms['ca'],
                'nom_es' => $noms['es'],
                'nom_eu' => $noms['eu'],
                'nom_gl' => $noms['gl'],
                'nom_pt' => $noms['pt'],
                'nom_it' => $noms['it'],
                'nom_fr' => $noms['fr'],
                'nom_en' => $noms['en'],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('mst_allergens', function (Blueprint $table) {
            $table->dropColumn(['nom_es', 'nom_eu', 'nom_gl', 'nom_pt', 'nom_it', 'nom_fr', 'nom_en']);
        });
    }
};
