<?php

namespace App\Console\Commands;

use App\Models\Food;
use Illuminate\Console\Command;

// Consulta de només lectura perquè la tasca programada generate-food-icons pugui triar
// quins aliments processar sense necessitar accés ampli a `php artisan tinker` (que
// permetria executar codi PHP arbitrari en una execució desatesa). Només llegeix
// aliments de la biblioteca pública (nutricionistaId NULL) sense imageUrl.
class FoodsWithoutIcon extends Command
{
    protected $signature = 'foods:without-icon {--limit=25 : Nombre màxim de files a mostrar}';

    protected $description = "Llista aliments de la biblioteca pública sense imageUrl (ús exclusiu de la tasca programada generate-food-icons)";

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $foods = Food::whereNull('imageUrl')
            ->whereNull('nutricionistaId')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'calories', 'categoryId']);

        foreach ($foods as $food) {
            $this->line("{$food->id}\t{$food->name}\t{$food->slug}\t{$food->calories}\t{$food->categoryId}");
        }

        $this->info("Total mostrat: {$foods->count()} (límit {$limit})");

        return self::SUCCESS;
    }
}
