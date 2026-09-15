<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Afegeix informació nutricional a la biblioteca d'aliments: pes estàndard de ració,
 * calories i macronutrients (proteïna, greix, hidrats, fibra) que aporta aquesta ració,
 * i quatre micronutrients (sodi, calci, ferro, vitamina B12) que solen ser rellevants en
 * consulta de nutrició. La "vitamina A" es guarda com a equivalents de retinol (RAE) i la
 * "vitamina B" com a vitamina B12 (µg) — és la vitamina del grup B que es sol vigilar de
 * manera aïllada (dèficit habitual en dietes vegetals) i l'única que es mesura en µg; la
 * resta del grup B es mesuren en mg i no s'inclouen per no barrejar unitats sota un mateix
 * camp.
 *
 * Les dades de partida són valors per 100 g/ml (font: taules de composició d'aliments
 * d'ús habitual en nutrició clínica espanyola, tipus BEDCA/USDA) i es converteixen aquí
 * mateix a la ració estàndard de cada aliment (mida de ració ajustada a les recomanacions
 * de la Guía de Alimentación Saludable de la SENC i a les taules de raciones de intercambio
 * habituals en consulta dietètica).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->unsignedInteger('standardGrams')->nullable()->after('imageUrl');
            $table->string('servingDescription', 80)->nullable()->after('standardGrams');
            $table->decimal('calories', 6, 1)->nullable()->after('servingDescription');
            $table->decimal('proteinGrams', 6, 2)->nullable()->after('calories');
            $table->decimal('fatGrams', 6, 2)->nullable()->after('proteinGrams');
            $table->decimal('carbsGrams', 6, 2)->nullable()->after('fatGrams');
            $table->decimal('fiberGrams', 6, 2)->nullable()->after('carbsGrams');
            $table->decimal('sodiumMg', 7, 1)->nullable()->after('fiberGrams');
            $table->decimal('calciumMg', 7, 1)->nullable()->after('sodiumMg');
            $table->decimal('ironMg', 6, 2)->nullable()->after('calciumMg');
            $table->decimal('vitaminAMcg', 7, 1)->nullable()->after('ironMg');
            $table->decimal('vitaminBMcg', 6, 2)->nullable()->after('vitaminAMcg');
        });

        // [kcal, protein, fat, carbs, fiber, sodium, calcium, iron, vitA, vitB12] per 100 g/ml
        $per100 = [
            // Verdures — ració estàndard 200 g
            'Ceba' => [40, 1.1, 0.1, 9.3, 1.7, 4, 23, 0.2, 0, 0],
            'Pebrot' => [31, 1.0, 0.3, 6.0, 2.1, 3, 10, 0.4, 157, 0],
            'Bròquil' => [34, 2.8, 0.4, 7.0, 2.6, 33, 47, 0.7, 31, 0],
            'Carbassó' => [17, 1.2, 0.3, 3.1, 1.0, 8, 16, 0.4, 10, 0],
            'Espinacs' => [23, 2.9, 0.4, 3.6, 2.2, 79, 99, 2.7, 469, 0],
            'All' => [149, 6.4, 0.5, 33.0, 2.1, 17, 181, 1.7, 0, 0],
            'Pastanaga' => [41, 0.9, 0.2, 10.0, 2.8, 69, 33, 0.3, 835, 0],
            'Tomàquet' => [18, 0.9, 0.2, 3.9, 1.2, 5, 10, 0.3, 42, 0],
            'Bolets' => [22, 3.1, 0.3, 3.3, 1.0, 5, 3, 0.5, 0, 0],
            'Amanida' => [15, 1.4, 0.2, 2.9, 1.3, 28, 36, 0.9, 166, 0],
            'Albergínia' => [25, 1.0, 0.2, 6.0, 3.0, 2, 9, 0.2, 1, 0],
            // Fruita — ració estàndard 150 g (llevat que s'indiqui una mida pròpia)
            'Poma' => [52, 0.3, 0.2, 14.0, 2.4, 1, 6, 0.1, 3, 0],
            'Síndria' => [30, 0.6, 0.2, 7.6, 0.4, 1, 7, 0.2, 28, 0],
            'Alvocat' => [160, 2.0, 15.0, 9.0, 7.0, 7, 12, 0.6, 7, 0],
            'Meló' => [34, 0.8, 0.2, 8.0, 0.9, 16, 11, 0.2, 169, 0],
            'Kiwi' => [61, 1.1, 0.5, 15.0, 3.0, 3, 34, 0.3, 4, 0],
            'Llimona' => [29, 1.1, 0.3, 9.3, 2.8, 2, 26, 0.6, 1, 0],
            'Pera' => [57, 0.4, 0.1, 15.0, 3.1, 1, 9, 0.2, 1, 0],
            'Fruits vermells' => [43, 0.9, 0.4, 10.0, 4.5, 1, 22, 0.6, 3, 0],
            'Mango' => [60, 0.8, 0.4, 15.0, 1.6, 1, 11, 0.2, 54, 0],
            'Nabius' => [57, 0.7, 0.3, 14.0, 2.4, 1, 6, 0.3, 3, 0],
            'Raïm' => [69, 0.7, 0.2, 18.0, 0.9, 2, 10, 0.4, 3, 0],
            'Taronja' => [47, 0.9, 0.1, 12.0, 2.4, 0, 40, 0.1, 11, 0],
            'Préssec' => [39, 0.9, 0.3, 10.0, 1.5, 0, 6, 0.3, 16, 0],
            'Coco' => [354, 3.3, 33.0, 15.0, 9.0, 20, 14, 2.4, 0, 0],
            'Castanyes' => [213, 2.4, 2.3, 45.0, 4.3, 3, 27, 1.0, 1, 0],
            'Plàtan' => [89, 1.1, 0.3, 23.0, 2.6, 1, 5, 0.3, 3, 0],
            'Cireres' => [63, 1.1, 0.2, 16.0, 2.1, 0, 13, 0.4, 3, 0],
            'Pinya' => [50, 0.5, 0.1, 13.0, 1.4, 1, 13, 0.3, 3, 0],
            // Cereals i tubercles
            'Pa integral' => [247, 13.0, 3.4, 41.0, 7.0, 400, 50, 2.5, 0, 0],
            'Blat de moro' => [86, 3.2, 1.2, 19.0, 2.7, 15, 2, 0.5, 9, 0],
            'Civada' => [389, 17.0, 7.0, 66.0, 10.6, 2, 54, 4.7, 0, 0],
            'Bagel' => [250, 10.0, 1.5, 49.0, 2.0, 430, 20, 3.0, 0, 0],
            'Arròs blanc' => [365, 7.1, 0.7, 80.0, 1.3, 1, 28, 0.8, 0, 0],
            'Pasta integral' => [348, 13.0, 2.5, 66.0, 8.0, 5, 20, 2.0, 0, 0],
            'Patata' => [77, 2.0, 0.1, 17.0, 2.2, 6, 12, 0.8, 0, 0],
            'Pasta blanca' => [371, 13.0, 1.5, 75.0, 3.2, 6, 18, 1.3, 0, 0],
            'Pa blanc' => [265, 9.0, 3.2, 49.0, 2.7, 490, 151, 3.6, 0, 0],
            'Baguet' => [270, 9.0, 1.2, 55.0, 2.4, 540, 30, 3.5, 0, 0],
            'Arròs integral' => [370, 7.9, 2.9, 77.0, 3.5, 5, 10, 1.5, 0, 0],
            'Quinoa' => [368, 14.0, 6.0, 64.0, 7.0, 5, 47, 4.6, 1, 0],
            'Moniato' => [86, 1.6, 0.1, 20.0, 3.0, 55, 30, 0.6, 709, 0],
            // Greixos i olis — ració estàndard 10 g (1 cullerada)
            "Oli d'oliva" => [884, 0, 100.0, 0, 0, 2, 1, 0.6, 0, 0],
            'Mantega' => [717, 0.9, 81.0, 0.1, 0, 11, 24, 0, 684, 0],
            'Oli de gira-sol' => [884, 0, 100.0, 0, 0, 0, 0, 0, 0, 0],
            // Llegums — ració estàndard 70 g en cru (llevat pèsols/edamame)
            'Faves' => [341, 26.0, 1.5, 58.0, 25.0, 13, 103, 6.7, 3, 0],
            'Llenties' => [353, 25.0, 1.1, 60.0, 11.0, 6, 35, 7.5, 2, 0],
            'Edamame' => [121, 12.0, 5.0, 10.0, 5.0, 6, 63, 2.3, 4, 0],
            'Mongetes' => [333, 21.0, 1.2, 60.0, 15.0, 16, 163, 6.7, 0, 0],
            'Pèsols' => [81, 5.4, 0.4, 14.0, 5.7, 5, 25, 1.5, 38, 0],
            'Cigrons' => [364, 19.0, 6.0, 61.0, 17.0, 24, 57, 6.2, 3, 0],
            'Hummus' => [166, 8.0, 9.6, 14.0, 6.0, 380, 38, 1.7, 1, 0],
            // Làctics i alternatives
            'Llet' => [61, 3.2, 3.3, 4.8, 0, 44, 113, 0, 28, 0.4],
            'Beguda de soja enriquida' => [33, 3.3, 1.8, 1.0, 0.6, 51, 120, 0.5, 1, 0.4],
            'Formatge' => [380, 25.0, 31.0, 1.3, 0, 620, 700, 0.5, 220, 1.3],
            'Iogurt natural' => [61, 3.5, 3.3, 4.7, 0, 46, 121, 0.1, 27, 0.5],
            'Iogurt grec' => [97, 9.0, 5.0, 4.0, 0, 36, 110, 0.1, 30, 0.5],
            // Proteïnes
            'Peix blau' => [200, 20.0, 13.0, 0, 0, 90, 30, 1.5, 40, 8.9],
            'Salmó' => [208, 20.0, 13.0, 0, 0, 59, 12, 0.5, 58, 3.2],
            'Tofu' => [76, 8.0, 4.8, 1.9, 0.3, 7, 350, 5.4, 0, 0],
            'Pollastre' => [165, 31.0, 3.6, 0, 0, 74, 15, 0.9, 9, 0.3],
            'Vedella' => [217, 26.0, 12.0, 0, 0, 66, 12, 2.6, 0, 2.1],
            'Porc' => [242, 27.0, 14.0, 0, 0, 62, 10, 0.9, 2, 0.7],
            'Ostres' => [68, 7.0, 2.5, 3.9, 0, 90, 45, 5.0, 90, 16.0],
            'Fruits secs' => [600, 20.0, 52.0, 20.0, 10.0, 1, 150, 3.5, 0, 0],
            'Llavors de xia' => [486, 17.0, 31.0, 42.0, 34.0, 16, 631, 7.7, 1, 0],
            'Gambes' => [85, 20.0, 0.5, 0.2, 0, 110, 52, 0.5, 0, 1.1],
            'Ou' => [155, 13.0, 11.0, 1.1, 0, 124, 50, 1.8, 160, 0.9],
            // Processats i begudes
            'Embotits' => [300, 20.0, 25.0, 1.0, 0, 1200, 10, 1.0, 0, 1.0],
            'Alcohol' => [250, 0, 0, 0, 0, 0, 0, 0, 0, 0],
            'Vi negre' => [85, 0.1, 0, 2.6, 0, 4, 8, 0.5, 0, 0],
            'Xocolata' => [546, 7.8, 31.0, 46.0, 11.0, 20, 73, 11.0, 3, 0.3],
            'Cupcake' => [380, 4.0, 18.0, 50.0, 1.0, 300, 50, 1.0, 20, 0.1],
            'Galetes' => [450, 7.0, 16.0, 70.0, 3.0, 400, 40, 2.0, 0, 0],
            'Cafè' => [1, 0.1, 0, 0, 0, 2, 2, 0, 0, 0],
            'Te' => [1, 0, 0, 0.3, 0, 1, 0, 0, 0, 0],
            'Cervesa' => [43, 0.5, 0, 3.6, 0, 4, 4, 0, 0, 0],
            'Donut' => [452, 5.0, 25.0, 51.0, 1.5, 320, 20, 1.5, 10, 0.1],
            'Flam' => [150, 4.0, 3.0, 25.0, 0, 60, 90, 0.3, 60, 0.3],
            'Patates fregides' => [312, 3.4, 15.0, 41.0, 3.8, 210, 12, 0.7, 0, 0],
            'Crispetes' => [375, 11.0, 4.0, 74.0, 15.0, 5, 7, 3.0, 0, 0],
            'Conserves' => [130, 26.0, 3.0, 0, 0, 300, 10, 1.4, 0, 2.5],
            'Gelat' => [207, 3.5, 11.0, 24.0, 0.7, 80, 128, 0.1, 44, 0.2],
            'Sal' => [0, 0, 0, 0, 0, 38758, 24, 0.3, 0, 0],
            'Mel' => [304, 0.3, 0, 82.0, 0.2, 4, 6, 0.4, 0, 0],
            'Brioixeria' => [406, 8.0, 21.0, 46.0, 2.0, 400, 30, 1.8, 50, 0.1],
            'Pastís' => [350, 5.0, 15.0, 50.0, 1.0, 300, 60, 1.5, 40, 0.1],
            'Begudes ensucrades' => [42, 0, 0, 10.6, 0, 5, 0, 0, 0, 0],
            'Caramels' => [390, 0, 0, 98.0, 0, 40, 2, 0, 0, 0],
            // Plats preparats
            'Sopa de fideus' => [35, 1.5, 1.0, 5.0, 0.5, 350, 10, 0.3, 10, 0],
            'Sushi' => [150, 5.0, 1.0, 30.0, 1.0, 300, 10, 0.5, 5, 1.0],
            'Frankfurt' => [290, 11.0, 26.0, 3.0, 0, 900, 10, 1.0, 0, 0.6],
            'Pizza' => [266, 11.0, 10.0, 33.0, 2.3, 600, 180, 2.0, 50, 0.4],
            'Hamburguesa' => [250, 13.0, 12.0, 22.0, 1.5, 500, 80, 2.0, 20, 0.6],
        ];

        // Ració estàndard (grams) i descripció curta, per aliment. Els que no surten aquí
        // fan servir el valor per defecte de la seva categoria (definit a $categoryDefaults).
        $standardGrams = [
            'Coco' => [30, '2 cullerades rasses (30 g)'],
            'Castanyes' => [50, "un grapat (50 g)"],
            'Pa integral' => [40, '1 llesca (40 g)'],
            'Pa blanc' => [40, '1 llesca (40 g)'],
            'Baguet' => [40, '1 tros (40 g)'],
            'Bagel' => [90, '1 unitat (90 g)'],
            'Blat de moro' => [150, '1 tassa (150 g)'],
            'Civada' => [40, '4 cullerades (40 g), en cru'],
            'Arròs blanc' => [60, '1 tassa en cru (60 g)'],
            'Arròs integral' => [60, '1 tassa en cru (60 g)'],
            'Pasta blanca' => [60, '1 ració en cru (60 g)'],
            'Pasta integral' => [60, '1 ració en cru (60 g)'],
            'Quinoa' => [40, '4 cullerades en cru (40 g)'],
            'Patata' => [150, '1 unitat mitjana (150 g)'],
            'Moniato' => [150, '1 unitat mitjana (150 g)'],
            "Oli d'oliva" => [10, '1 cullerada (10 g)'],
            'Oli de gira-sol' => [10, '1 cullerada (10 g)'],
            'Mantega' => [10, '1 cullerada (10 g)'],
            'Edamame' => [100, "1 bol amb tavella (100 g)"],
            'Pèsols' => [100, '1 tassa (100 g)'],
            'Hummus' => [40, '2 cullerades (40 g)'],
            'Llet' => [200, '1 got (200 ml)'],
            'Beguda de soja enriquida' => [200, '1 got (200 ml)'],
            'Formatge' => [30, '2-3 llesques (30 g)'],
            'Iogurt natural' => [125, '1 unitat (125 g)'],
            'Iogurt grec' => [125, '1 unitat (125 g)'],
            'Peix blau' => [125, '1 filet (125 g)'],
            'Salmó' => [125, '1 filet (125 g)'],
            'Tofu' => [100, '1 tall (100 g)'],
            'Pollastre' => [125, '1 pit (125 g)'],
            'Vedella' => [125, '1 filet (125 g)'],
            'Porc' => [125, '1 filet (125 g)'],
            'Ostres' => [100, '6 unitats (100 g)'],
            'Fruits secs' => [25, '1 grapat (25 g)'],
            'Llavors de xia' => [15, '1 cullerada (15 g)'],
            'Gambes' => [100, '1 ració (100 g)'],
            'Ou' => [60, '1 unitat (60 g)'],
            'Embotits' => [30, '3-4 llesques (30 g)'],
            'Alcohol' => [30, '1 got de xarrup (30 ml)'],
            'Vi negre' => [100, '1 copa (100 ml)'],
            'Xocolata' => [20, '2 onces (20 g)'],
            'Cupcake' => [60, '1 unitat (60 g)'],
            'Galetes' => [30, '4 unitats (30 g)'],
            'Cafè' => [200, '1 tassa (200 ml)'],
            'Te' => [200, '1 tassa (200 ml)'],
            'Cervesa' => [250, '1 canya (250 ml)'],
            'Donut' => [60, '1 unitat (60 g)'],
            'Flam' => [100, '1 unitat (100 g)'],
            'Patates fregides' => [60, '1 bossa petita (60 g)'],
            'Crispetes' => [30, '1 bossa petita (30 g)'],
            'Conserves' => [80, '1/2 llauna (80 g)'],
            'Gelat' => [100, '2 boles (100 g)'],
            'Sal' => [5, '1 culleradeta (5 g)'],
            'Mel' => [15, '1 cullerada (15 g)'],
            'Brioixeria' => [60, '1 unitat (60 g)'],
            'Pastís' => [80, '1 tall (80 g)'],
            'Begudes ensucrades' => [330, '1 llauna (330 ml)'],
            'Caramels' => [10, '2-3 unitats (10 g)'],
            'Sopa de fideus' => [250, '1 bol (250 ml)'],
            'Sushi' => [180, '8 peces (180 g)'],
            'Frankfurt' => [50, '1 unitat (50 g)'],
            'Pizza' => [150, '1 tall (150 g)'],
            'Hamburguesa' => [200, '1 unitat sencera (200 g)'],
        ];

        $categoryDefaultGrams = [
            'Verdures' => [200, 'ració estàndard (200 g)'],
            'Fruita' => [150, '1 unitat mitjana (150 g)'],
            'Cereals i tubercles' => [60, 'ració estàndard (60 g)'],
            'Greixos i olis' => [10, '1 cullerada (10 g)'],
            'Llegums' => [70, 'en cru (70 g)'],
            'Làctics i alternatives' => [150, 'ració estàndard (150 g)'],
            'Proteïnes' => [100, 'ració estàndard (100 g)'],
            'Processats i begudes' => [50, 'ració estàndard (50 g)'],
            'Plats preparats' => [200, 'ració estàndard (200 g)'],
        ];

        // Aliments nous que la biblioteca no tenia i que són habituals en consulta de nutrició.
        $newFoods = [
            ['name' => 'Vedella', 'category' => 'Proteïnes', 'image' => 'vedella'],
            ['name' => 'Porc', 'category' => 'Proteïnes', 'image' => 'porc'],
            ['name' => 'Salmó', 'category' => 'Proteïnes', 'image' => 'salmo'],
            ['name' => 'Llavors de xia', 'category' => 'Proteïnes', 'image' => 'llavors-de-xia'],
            ['name' => 'Iogurt grec', 'category' => 'Làctics i alternatives', 'image' => 'iogurt-grec'],
            ['name' => 'Hummus', 'category' => 'Llegums', 'image' => 'hummus'],
            ['name' => 'Cogombre', 'category' => 'Verdures', 'image' => 'cogombre'],
            ['name' => 'Espàrrecs', 'category' => 'Verdures', 'image' => 'esparrecs'],
            ['name' => 'Carbassa', 'category' => 'Verdures', 'image' => 'carbassa'],
            ['name' => 'Vi negre', 'category' => 'Processats i begudes', 'image' => 'vi-negre'],
        ];

        $categoryIdByName = DB::table('food_categories')->pluck('id', 'name');

        // Cogombre, Espàrrecs i Carbassa fan servir la mateixa taula de verdures (200 g)
        $per100['Cogombre'] = [15, 0.7, 0.1, 3.6, 0.5, 2, 16, 0.3, 5, 0];
        $per100['Espàrrecs'] = [20, 2.2, 0.1, 3.9, 2.1, 2, 24, 2.1, 38, 0];
        $per100['Carbassa'] = [26, 1.0, 0.1, 6.5, 0.5, 1, 21, 0.8, 426, 0];

        $existingFoods = DB::table('foods')->select('id', 'name')->get()->keyBy('name');

        foreach ($per100 as $name => $vals) {
            [$kcal, $protein, $fat, $carbs, $fiber, $sodium, $calcium, $iron, $vitA, $vitB] = $vals;

            [$grams, $description] = $standardGrams[$name] ?? [null, null];

            $existing = $existingFoods[$name] ?? null;
            $categoryName = null;
            $imageSlug = null;
            foreach ($newFoods as $nf) {
                if ($nf['name'] === $name) {
                    $categoryName = $nf['category'];
                    $imageSlug = $nf['image'];
                }
            }

            if ($grams === null) {
                // Cap a la ració per defecte de la categoria (aliments existents sense mida pròpia)
                $catForDefault = $categoryName;
                if (! $catForDefault && $existing) {
                    $catForDefault = DB::table('food_categories')->where('id', DB::table('foods')->where('id', $existing->id)->value('categoryId'))->value('name');
                }
                [$grams, $description] = $categoryDefaultGrams[$catForDefault] ?? [100, 'ració estàndard (100 g)'];
            }

            $scale = $grams / 100;
            $row = [
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
            ];

            if ($existing) {
                DB::table('foods')->where('id', $existing->id)->update($row);
            } elseif ($categoryName) {
                $categoryId = $categoryIdByName[$categoryName] ?? null;
                if ($categoryId) {
                    DB::table('foods')->insert(array_merge($row, [
                        'id' => (string) Str::uuid(),
                        'name' => $name,
                        'slug' => Str::slug($name),
                        'description' => null,
                        'imageUrl' => $imageSlug ? '/foods/'.$imageSlug.'.svg' : null,
                        'categoryId' => $categoryId,
                    ]));
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->dropColumn([
                'standardGrams',
                'servingDescription',
                'calories',
                'proteinGrams',
                'fatGrams',
                'carbsGrams',
                'fiberGrams',
                'sodiumMg',
                'calciumMg',
                'ironMg',
                'vitaminAMcg',
                'vitaminBMcg',
            ]);
        });
    }
};
