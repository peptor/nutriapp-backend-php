<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Passa a opcionals 21 camps de les 11 rutines originals de la biblioteca: esdeveniments "quan hi ha
// símptoma" i text lliure, camps que no apliquen cada dia (entrenament) i extres de l'estil de vida
// que no són l'objectiu de la rutina. Es mantenen obligatoris la mesura principal i la intervenció
// central de cada rutina. Només afecta les plantilles noves creades des de la biblioteca.
// Proposta pendent de validació clínica: docs/pendent-validacio-nutricionista.md (secció 4).
return new class extends Migration
{
    // [patró de la rutina, nom del camp]
    private const FIELDS = [
        ['Celiaquia%', 'cross_contact'],
        ['Celiaquia%', 'label_check'],
        ['Celiaquia%', 'notes'],
        ['Control de colesterol%', 'activity_minutes'],
        ['Diabetis: mètode%', 'hypo'],
        ['Malaltia renal%', 'labs_note'],
        ['Nutrició esportiva%', 'pre_workout_meal'],
        ['Nutrició esportiva%', 'post_workout_meal'],
        ['Nutrició esportiva%', 'perceived_effort'],
        ['Nutrició esportiva%', 'sleep_hours'],
        ['Patró DASH%', 'water'],
        ['Patró saludable%', 'water'],
        ['Patró saludable%', 'hunger'],
        ['Patró saludable%', 'notes'],
        ['Postoperatori%', 'nausea_vomiting'],
        ['Reflux gastroesofàgic%', 'regurgitation'],
        ['Reflux gastroesofàgic%', 'trigger_food'],
        ['Rutina per restrenyiment%', 'pain'],
        ['Rutina per restrenyiment%', 'activity'],
        ['SII%', 'stress'],
        ['SII%', 'suspected_trigger'],
    ];

    private function set(int $required): void
    {
        foreach (self::FIELDS as [$pattern, $name]) {
            $routineId = DB::table('mst_library_routines')->where('name', 'like', $pattern)->value('id');
            if ($routineId) {
                DB::table('mst_library_routine_fields')
                    ->where('libraryRoutineId', $routineId)
                    ->where('name', $name)
                    ->update(['required' => $required]);
            }
        }
    }

    public function up(): void
    {
        $this->set(0);
    }

    public function down(): void
    {
        $this->set(1);
    }
};
