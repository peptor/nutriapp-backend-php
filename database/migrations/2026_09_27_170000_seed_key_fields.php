<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Decisió 5 (docs/disseny-migracions-regles-camps.md): camps clau per rutina de la biblioteca, els que es miraran per
// dir si el progrés és bo o dolent. Proposta de la IA (docs/nutrievo-camps-rutines-biblioteca.xlsx, columna "Camp clau"):
// pendent de validar. Es marca a la biblioteca i a les plantilles que en venen (sourceLibraryFieldId). El TCA no en té.
return new class extends Migration
{
    private const KEY_FIELDS = [
        'Control de colesterol i salut cardiovascular' => ['healthy_fats', 'fiber_portions', 'saturated_fat_choices'],
        'Hipertensió arterial: hàbits per controlar la pressió' => ['bp', 'salt_choices', 'alcohol_units'],
        'Patró DASH i reducció de sodi' => ['bp', 'sodium_choices', 'processed_food'],
        'Celiaquia: seguiment sense gluten' => ['gluten_free_meals', 'symptoms', 'cross_contact'],
        'Malaltia inflamatòria intestinal: registre de símptomes' => ['wellbeing_04', 'pain_03', 'liquid_stools', 'blood_stool'],
        'Reflux gastroesofàgic (RGE)' => ['heartburn', 'regurgitation', 'late_meal'],
        'Rutina per restrenyiment' => ['bowel_movements', 'bristol', 'straining'],
        'SII: seguiment FODMAP supervisat' => ['bloating', 'pain', 'bristol'],
        'Disfàgia: textures i seguretat en menjar' => ['cough_episodes', 'intake_pct', 'weight'],
        'Diabetis gestacional: alimentació i glucosa' => ['glucose_fasting', 'glucose_1h', 'glucose_after'],
        'Embaràs: pes gestacional i hàbits' => ['weight', 'nausea_vomiting', 'supplements'],
        'Nutrició esportiva i rendiment' => ['training_minutes', 'hydration', 'perceived_effort'],
        'Gent gran: prevenció de la malnutrició' => ['intake_pct', 'weight', 'appetite'],
        'Fetge gras (MASLD): pes, alcohol i dieta mediterrània' => ['weight', 'waist_cm', 'alcohol_units', 'medas_score'],
        'Diabetis: mètode del plat' => ['glucose_fasting', 'glucose_after', 'medication'],
        'Adherència a la dieta mediterrània' => ['medas_week_score'],
        'Patró saludable i control de pes' => ['weight', 'fruit_vegetables', 'meals'],
        'Oncologia: suport nutricional' => ['intake_pct', 'weight', 'appetite'],
        'Pediatria: hàbits familiars 5-2-1-0' => ['fruit_veg', 'screen_time', 'activity_minutes', 'sugary_drinks'],
        'Postoperatori de cirurgia bariàtrica' => ['protein_intake', 'fluid_intake', 'tolerance'],
        'Malaltia renal crònica (sense diàlisi)' => ['fluid_intake', 'protein_portions', 'swelling'],
    ];

    public function up(): void
    {
        $this->mark(true);
    }

    public function down(): void
    {
        $this->mark(false);
    }

    private function mark(bool $value): void
    {
        foreach (self::KEY_FIELDS as $routineName => $names) {
            $routineId = DB::table('mst_library_routines')->where('name', $routineName)->value('id');
            if (! $routineId) {
                continue;
            }
            $libraryIds = DB::table('mst_library_routine_fields')->where('libraryRoutineId', $routineId)->whereIn('name', $names)->pluck('id');
            DB::table('mst_library_routine_fields')->whereIn('id', $libraryIds)->update(['isKeyField' => $value]);
            DB::table('mst_routine_fields')->whereIn('sourceLibraryFieldId', $libraryIds)->update(['isKeyField' => $value]);
        }
    }
};
