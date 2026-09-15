<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Segona ronda d'aliments habituals que faltaven a la biblioteca (després que un usuari
// trobés a faltar el peix blanc, es va fer una auditoria sistemàtica per categoria en lloc
// de seguir afegint d'un en un). Mateix format que 2026_09_14_174620: valors per 100 g
// (taules USDA/BEDCA) escalats a la ració estàndard de cada aliment.
return new class extends Migration
{
    public function up(): void
    {
        // [kcal, proteïna, greix, hidrats, fibra, sodi mg, calci mg, ferro mg, vitA mcg, vitB mcg] per 100 g
        $per100 = [
            'Tonyina' => [116, 25.5, 1.0, 0, 0, 247, 12, 1.3, 17, 2.5],
            'Gall dindi' => [135, 30.0, 1.0, 0, 0, 50, 5, 0.7, 0, 0.3],
            'Formatge fresc' => [80, 12.0, 2.0, 4.0, 0, 150, 90, 0.1, 30, 0.4],
            'Col' => [25, 1.3, 0.1, 5.8, 2.5, 18, 40, 0.5, 5, 0],
            'Coliflor' => [25, 1.9, 0.3, 5.0, 2.0, 30, 22, 0.4, 0, 0],
            'Mandarina' => [53, 0.8, 0.3, 13.3, 1.8, 2, 37, 0.2, 34, 0],
            'Figues' => [74, 0.8, 0.3, 19.2, 2.9, 1, 35, 0.4, 7, 0],
            'Cuscús' => [376, 12.8, 0.6, 77.4, 5.0, 10, 24, 1.1, 0, 0],
            'Truita de patates' => [195, 7.0, 14.0, 10.0, 1.0, 250, 30, 1.0, 60, 0.5],
        ];

        $standardGrams = [
            'Tonyina' => [80, '1 llauna escorreguda (80 g)'],
            'Gall dindi' => [125, '1 filet (125 g)'],
            'Formatge fresc' => [125, '1 pot (125 g)'],
            'Mandarina' => [100, '2 unitats (100 g)'],
            'Figues' => [100, '2 unitats (100 g)'],
            'Cuscús' => [60, '4 cullerades en cru (60 g)'],
            'Truita de patates' => [150, '1 tall (150 g)'],
        ];

        $categoryByFood = [
            'Tonyina' => 'Proteïnes',
            'Gall dindi' => 'Proteïnes',
            'Formatge fresc' => 'Làctics i alternatives',
            'Col' => 'Verdures',
            'Coliflor' => 'Verdures',
            'Mandarina' => 'Fruita',
            'Figues' => 'Fruita',
            'Cuscús' => 'Cereals i tubercles',
            'Truita de patates' => 'Plats preparats',
        ];

        $categoryDefaultGrams = [
            'Verdures' => [200, 'ració estàndard (200 g)'],
            'Fruita' => [150, '1 unitat mitjana (150 g)'],
        ];

        $imageSlugs = [
            'Tonyina' => 'tonyina',
            'Gall dindi' => 'gall-dindi',
            'Formatge fresc' => 'formatge-fresc',
            'Col' => 'col',
            'Coliflor' => 'coliflor',
            'Mandarina' => 'mandarina',
            'Figues' => 'figues',
            'Cuscús' => 'cuscus',
            'Truita de patates' => 'truita-de-patates',
        ];

        $categoryIdByName = DB::table('food_categories')->pluck('id', 'name');

        foreach ($per100 as $name => $vals) {
            if (DB::table('foods')->where('name', $name)->exists()) {
                continue;
            }

            $categoryName = $categoryByFood[$name];
            $categoryId = $categoryIdByName[$categoryName] ?? null;
            if (! $categoryId) {
                continue;
            }

            [$kcal, $protein, $fat, $carbs, $fiber, $sodium, $calcium, $iron, $vitA, $vitB] = $vals;
            [$grams, $description] = $standardGrams[$name] ?? $categoryDefaultGrams[$categoryName] ?? [100, 'ració estàndard (100 g)'];
            $scale = $grams / 100;

            DB::table('foods')->insert([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => null,
                'imageUrl' => '/foods/'.$imageSlugs[$name].'.svg',
                'categoryId' => $categoryId,
                'standardGrams' => $grams,
                'servingDescription' => $description,
                'calories' => round($kcal * $scale, 1),
                'proteinGrams' => round($protein * $scale, 2),
                'fatGrams' => round($fat * $scale, 2),
                'carbsGrams' => round($carbs * $scale, 2),
                'fiberGrams' => round($fiber * $scale, 2),
                'sodiumMg' => round($sodium * $scale, 1),
                'calciumMg' => round($calcium * $scale, 1),
                'ironMg' => round($iron * $scale, 2),
                'vitaminAMcg' => round($vitA * $scale, 1),
                'vitaminBMcg' => round($vitB * $scale, 2),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('foods')->whereIn('name', [
            'Tonyina', 'Gall dindi', 'Formatge fresc', 'Col', 'Coliflor',
            'Mandarina', 'Figues', 'Cuscús', 'Truita de patates',
        ])->delete();
    }
};
