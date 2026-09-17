<?php

namespace App\Console\Commands;

use App\Models\Food;
use Illuminate\Console\Command;

// Comanda estreta i d'un sol propòsit perquè la tasca programada generate-food-icons
// pugui assignar imageUrl sense necessitar accés ampli a `php artisan tinker` (que permetria
// executar codi PHP arbitrari contra la BD en una execució desatesa). Només toca
// mst_foods.imageUrl, i només per a aliments de la biblioteca pública (nutricionistaId NULL) —
// mai aliments personalitzats d'un nutricionista.
class SetFoodIcon extends Command
{
    protected $signature = 'foods:set-icon {id : UUID de mst_foods} {filename : Nom del fitxer dins frontend/public/foods/, p.ex. poma.svg (sense barra inicial, evita el path-mangling de Git Bash a Windows)}';

    protected $description = "Assigna imageUrl a un aliment de la biblioteca pública (ús exclusiu de la tasca programada generate-food-icons)";

    public function handle(): int
    {
        $id = (string) $this->argument('id');
        $filename = (string) $this->argument('filename');

        if (! preg_match('#^[a-z0-9\-]+\.svg$#', $filename)) {
            $this->error("El nom de fitxer ha de ser <slug>.svg (només minúscules, números i guions, sense barres).");

            return self::FAILURE;
        }
        $path = '/foods/'.$filename;

        $food = Food::find($id);
        if (! $food) {
            $this->error("Aliment no trobat: {$id}");

            return self::FAILURE;
        }
        if ($food->nutricionistaId !== null) {
            $this->error("Aquest aliment és personalitzat d'un nutricionista; aquesta comanda només toca la biblioteca pública.");

            return self::FAILURE;
        }

        $food->update(['imageUrl' => $path]);
        $this->info("OK: {$food->name} -> {$path}");

        return self::SUCCESS;
    }
}
