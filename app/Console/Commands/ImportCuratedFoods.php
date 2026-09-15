<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa l'excel curat "NutriEvo_aliments_curats_Ciqual2025_alergens" (transformat prèviament
 * a JSON per storage/app/ciqual_curated_transform.py) a mst_foods, mst_subcategory,
 * mst_translate i mst_food_allergen. Idempotent: cada aliment es reconeix pel seu
 * ciqualCode, així que re-executar-lo no duplica res, només actualitza.
 *
 * Els noms es guarden a mst_translate (nom_fr, de moment; la resta d'idiomes els omple
 * ciqual:translate-names --table=foods més endavant). Mentre no hi hagi nom_ca, mst_foods.name
 * es deixa amb el nom francès perquè la biblioteca no mostri buits.
 */
class ImportCuratedFoods extends Command
{
    protected $signature = 'foods:import-curated {--file=} {--dry-run}';

    protected $description = "Importa l'excel curat de Ciqual 2025 (transformat a JSON) a mst_foods";

    public function handle(): int
    {
        $path = $this->option('file') ?: storage_path('app/ciqual_curated_foods.json');
        if (! file_exists($path)) {
            $this->error("No trobo el fitxer: {$path}");

            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($path), true);
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Aliments al fitxer: '.count($data['foods']).' | Subcategories: '.count($data['subcategories']));

        $subcategoryIdBySlug = $this->importSubcategories($data['subcategories'], $dryRun);
        $allergenIdByCodi = DB::table('mst_allergens')->pluck('id', 'codi');

        $foodsCreated = 0;
        $foodsUpdated = 0;
        $allergenLinksTotal = 0;

        foreach ($data['foods'] as $row) {
            $existing = DB::table('mst_foods')->where('ciqualCode', $row['ciqualCode'])->first();

            $foodRow = array_merge($row['nutrients'], [
                'name' => $row['nomFr'],
                'slug' => Str::slug($row['nomFr']).'-'.substr($row['ciqualCode'], 0, 8),
                'standardGrams' => 100,
                'servingDescription' => 'Ració de 100 g',
                'calories' => $row['calories'],
                'categoryId' => $row['categoryId'],
                'subcategoryId' => $row['subcategorySlug'] ? ($subcategoryIdBySlug[$row['subcategorySlug']] ?? null) : null,
                'ciqualCode' => $row['ciqualCode'],
            ]);

            if ($dryRun) {
                $existing ? $foodsUpdated++ : $foodsCreated++;

                continue;
            }

            if ($existing) {
                DB::table('mst_foods')->where('id', $existing->id)->update($foodRow);
                $foodId = $existing->id;
                $foodsUpdated++;
            } else {
                $foodId = (string) Str::uuid();
                DB::table('mst_foods')->insert(array_merge($foodRow, ['id' => $foodId]));
                $foodsCreated++;
            }

            $this->upsertTranslationFr('food', $foodId, $row['nomFr']);

            DB::table('mst_food_allergen')->where('foodId', $foodId)->delete();
            foreach ($row['allergens'] as $allergen) {
                $allergenId = $allergenIdByCodi[$allergen['codi']] ?? null;
                if (! $allergenId) {
                    continue;
                }
                DB::table('mst_food_allergen')->insert([
                    'id' => (string) Str::uuid(),
                    'foodId' => $foodId,
                    'allergenId' => $allergenId,
                    'confianca' => $allergen['confianca'],
                ]);
                $allergenLinksTotal++;
            }
        }

        $this->info("Fet: {$foodsCreated} aliments nous, {$foodsUpdated} actualitzats, {$allergenLinksTotal} relacions d'al·lèrgens.");

        return self::SUCCESS;
    }

    private function importSubcategories(array $subcategories, bool $dryRun): array
    {
        $existing = DB::table('mst_subcategory')->pluck('id', 'sourceSlugFr');
        $map = $existing->toArray();

        foreach ($subcategories as $sub) {
            if (isset($map[$sub['slug']])) {
                continue;
            }
            if ($dryRun) {
                $map[$sub['slug']] = 'DRY-RUN';

                continue;
            }
            $id = (string) Str::uuid();
            DB::table('mst_subcategory')->insert(['id' => $id, 'sourceSlugFr' => $sub['slug']]);
            $this->upsertTranslationFr('subcategory', $id, $sub['nameFr']);
            $map[$sub['slug']] = $id;
        }

        return $map;
    }

    private function upsertTranslationFr(string $entityType, string $entityId, string $nomFr): void
    {
        $existing = DB::table('mst_translate')->where('entityType', $entityType)->where('entityId', $entityId)->first();
        if ($existing) {
            DB::table('mst_translate')->where('id', $existing->id)->update(['nom_fr' => $nomFr]);
        } else {
            DB::table('mst_translate')->insert([
                'id' => (string) Str::uuid(),
                'entityType' => $entityType,
                'entityId' => $entityId,
                'nom_fr' => $nomFr,
            ]);
        }
    }
}
