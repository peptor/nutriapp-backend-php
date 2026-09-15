<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// La biblioteca d'aliments tenia "Peix blau" i "Salmó" (peixos grassos) però cap peix blanc
// (lluç, bacallà, llobarro...), un dels aliments proteics més habituals a la dieta
// mediterrània i nutricionalment ben diferent (molt més magre). Dades per 100 g basades en
// lluç/bacallà cuit (mitjana de taules USDA/BEDCA), escalades a la mateixa ració de 125 g
// que ja s'usa per als altres peixos.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('foods')->where('name', 'Peix blanc')->exists()) {
            return;
        }

        $categoryId = DB::table('food_categories')->where('name', 'Proteïnes')->value('id');
        if (! $categoryId) {
            return;
        }

        // [kcal, proteïna, greix, hidrats, fibra, sodi mg, calci mg, ferro mg, vitA mcg, vitB mcg] per 100 g
        [$kcal, $protein, $fat, $carbs, $fiber, $sodium, $calcium, $iron, $vitA, $vitB] = [90, 18.0, 1.5, 0, 0, 70, 20, 0.5, 15, 1.5];
        $grams = 125;
        $scale = $grams / 100;

        DB::table('foods')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Peix blanc',
            'slug' => Str::slug('Peix blanc'),
            'description' => null,
            'imageUrl' => '/foods/peix-blanc.svg',
            'categoryId' => $categoryId,
            'standardGrams' => $grams,
            'servingDescription' => '1 filet (125 g)',
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

    public function down(): void
    {
        DB::table('foods')->where('name', 'Peix blanc')->delete();
    }
};
