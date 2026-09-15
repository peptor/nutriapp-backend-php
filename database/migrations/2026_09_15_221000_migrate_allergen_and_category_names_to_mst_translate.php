<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// mst_allergens no té encara cap consumidor (cap UI el mostra), així que és segur
// centralitzar-lo del tot ara mateix: es copien els seus noms a mst_translate i es
// desmunten les columnes nom_* de la pròpia taula.
//
// mst_food_categories sí que es fa servir arreu (selects, agrupacions...), així que aquí
// NOMÉS s'hi afegeix la traducció a mst_translate com a font de dades preparada per al
// futur; la columna `name` es queda tal qual per no trencar res ara mateix.
return new class extends Migration
{
    public function up(): void
    {
        $categoryTranslations = [
            'Verdures' => ['es' => 'Verduras', 'eu' => 'Barazkiak', 'gl' => 'Verduras', 'pt' => 'Vegetais', 'it' => 'Verdure', 'fr' => 'Légumes', 'en' => 'Vegetables'],
            'Fruita' => ['es' => 'Fruta', 'eu' => 'Fruta', 'gl' => 'Froita', 'pt' => 'Fruta', 'it' => 'Frutta', 'fr' => 'Fruits', 'en' => 'Fruit'],
            'Cereals i tubercles' => ['es' => 'Cereales y tubérculos', 'eu' => 'Zerealak eta tuberkuluak', 'gl' => 'Cereais e tubérculos', 'pt' => 'Cereais e tubérculos', 'it' => 'Cereali e tuberi', 'fr' => 'Céréales et tubercules', 'en' => 'Cereals and tubers'],
            'Greixos i olis' => ['es' => 'Grasas y aceites', 'eu' => 'Koipeak eta olioak', 'gl' => 'Graxas e aceites', 'pt' => 'Gorduras e óleos', 'it' => 'Grassi e oli', 'fr' => 'Graisses et huiles', 'en' => 'Fats and oils'],
            'Llegums' => ['es' => 'Legumbres', 'eu' => 'Lekaleak', 'gl' => 'Legumes', 'pt' => 'Leguminosas', 'it' => 'Legumi', 'fr' => 'Légumineuses', 'en' => 'Legumes'],
            'Làctics i alternatives' => ['es' => 'Lácteos y alternativas', 'eu' => 'Esnekiak eta ordezkoak', 'gl' => 'Lácteos e alternativas', 'pt' => 'Laticínios e alternativas', 'it' => 'Latticini e alternative', 'fr' => 'Produits laitiers et alternatives', 'en' => 'Dairy and alternatives'],
            'Proteïnes' => ['es' => 'Proteínas', 'eu' => 'Proteinak', 'gl' => 'Proteínas', 'pt' => 'Proteínas', 'it' => 'Proteine', 'fr' => 'Protéines', 'en' => 'Proteins'],
            'Processats i begudes' => ['es' => 'Procesados y bebidas', 'eu' => 'Prozesatuak eta edariak', 'gl' => 'Procesados e bebidas', 'pt' => 'Processados e bebidas', 'it' => 'Trasformati e bevande', 'fr' => 'Transformés et boissons', 'en' => 'Processed foods and beverages'],
            'Plats preparats' => ['es' => 'Platos preparados', 'eu' => 'Prestatutako platerak', 'gl' => 'Pratos preparados', 'pt' => 'Pratos preparados', 'it' => 'Piatti pronti', 'fr' => 'Plats préparés', 'en' => 'Ready meals'],
        ];

        foreach (DB::table('mst_food_categories')->get() as $category) {
            $t = $categoryTranslations[$category->name] ?? [];
            DB::table('mst_translate')->insert([
                'id' => (string) Str::uuid(),
                'entityType' => 'category',
                'entityId' => $category->id,
                'nom_ca' => $category->name,
                'nom_es' => $t['es'] ?? null,
                'nom_eu' => $t['eu'] ?? null,
                'nom_gl' => $t['gl'] ?? null,
                'nom_pt' => $t['pt'] ?? null,
                'nom_it' => $t['it'] ?? null,
                'nom_fr' => $t['fr'] ?? null,
                'nom_en' => $t['en'] ?? null,
            ]);
        }

        foreach (DB::table('mst_allergens')->get() as $allergen) {
            DB::table('mst_translate')->insert([
                'id' => (string) Str::uuid(),
                'entityType' => 'allergen',
                'entityId' => (string) $allergen->id,
                'nom_ca' => $allergen->nom,
                'nom_es' => $allergen->nom_es,
                'nom_eu' => $allergen->nom_eu,
                'nom_gl' => $allergen->nom_gl,
                'nom_pt' => $allergen->nom_pt,
                'nom_it' => $allergen->nom_it,
                'nom_fr' => $allergen->nom_fr,
                'nom_en' => $allergen->nom_en,
            ]);
        }

        Schema::table('mst_allergens', function (Blueprint $table) {
            $table->dropColumn(['nom_es', 'nom_eu', 'nom_gl', 'nom_pt', 'nom_it', 'nom_fr', 'nom_en']);
        });
    }

    public function down(): void
    {
        Schema::table('mst_allergens', function (Blueprint $table) {
            $table->string('nom_es')->nullable();
            $table->string('nom_eu')->nullable();
            $table->string('nom_gl')->nullable();
            $table->string('nom_pt')->nullable();
            $table->string('nom_it')->nullable();
            $table->string('nom_fr')->nullable();
            $table->string('nom_en')->nullable();
        });

        foreach (DB::table('mst_translate')->where('entityType', 'allergen')->get() as $t) {
            DB::table('mst_allergens')->where('id', $t->entityId)->update([
                'nom_es' => $t->nom_es, 'nom_eu' => $t->nom_eu, 'nom_gl' => $t->nom_gl,
                'nom_pt' => $t->nom_pt, 'nom_it' => $t->nom_it, 'nom_fr' => $t->nom_fr, 'nom_en' => $t->nom_en,
            ]);
        }

        DB::table('mst_translate')->whereIn('entityType', ['allergen', 'category'])->delete();
    }
};
