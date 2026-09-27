<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

// Regles per defecte de la biblioteca (Fase 1, docs/disseny-migracions-regles-camps.md). Es fixen per `name` de camp,
// com les migracions de dades anteriors. Només es toquen camps que encara no tenen regla (goodDirection nul).
// NO s'omple cap alertMin/alertMax: els llindars numèrics esperen que un nutricionista els validi.
// Les plantilles ja creades no canvien: les regles s'hereten només en crear-ne de noves des de la biblioteca.
return new class extends Migration
{
    // Números: direcció bona per defecte (LOW = com més baix millor, HIGH = com més alt, TARGET = mantenir-se dins l'objectiu).
    private const NUMBER_DIRECTION = [
        'activity' => 'HIGH',
        'activity_minutes' => 'HIGH',
        'alcohol_units' => 'LOW',
        'caffeine_cups' => 'LOW',
        'cough_episodes' => 'LOW',
        'fiber' => 'HIGH',
        'fiber_portions' => 'HIGH',
        'fluid_cups' => 'HIGH',
        'fruit_veg' => 'HIGH',
        'fruit_vegetables' => 'HIGH',
        'glucose_1h' => 'TARGET',
        'glucose_after' => 'TARGET',
        'glucose_bedtime' => 'TARGET',
        'glucose_before' => 'TARGET',
        'glucose_fasting' => 'TARGET',
        'gluten_free_meals' => 'HIGH',
        'healthy_fats' => 'HIGH',
        'heartburn_days' => 'LOW',
        'hydration' => 'HIGH',
        'intake_pct' => 'HIGH',
        'liquid_stools' => 'LOW',
        'meals' => 'HIGH',
        'pain_days' => 'LOW',
        'phosphorus_lab' => 'TARGET',
        'potassium_lab' => 'TARGET',
        'processed_food' => 'LOW',
        'protein_intake' => 'HIGH',
        'protein_portions' => 'HIGH',
        'saturated_fat_choices' => 'LOW',
        'screen_time' => 'LOW',
        'sleep_hours' => 'TARGET',
        'sugary_drinks' => 'LOW',
        'time_in_range' => 'HIGH',
        'vomiting_count' => 'LOW',
        'waist_cm' => 'LOW',
        'water' => 'HIGH',
        'whole_grains' => 'HIGH',
    ];

    // Excepcions per rutina (subcadena de "categoria | nom" en minúscules, camp, direcció).
    private const NUMBER_OVERRIDES = [
        ['fetge gras', 'weight', 'LOW'],
        ['hipertensi', 'weight', 'LOW'],
        ['oncologia', 'weight', 'HIGH'],
        ['gent gran', 'weight', 'TARGET'],
        ['disf', 'weight', 'TARGET'],
        ['embar', 'weight', 'TARGET'],
        ['bari', 'weight', 'LOW'],
        ['malaltia inflamat', 'weight', 'TARGET'],
        ['diabetis', 'weight', 'LOW'],
        ['patr', 'weight', 'LOW'],
        ['renal', 'fluid_intake', 'TARGET'],
        ['renal', 'protein_portions', 'TARGET'],
        ['renal', 'water', 'TARGET'],
        ['restrenyiment', 'bowel_movements', 'HIGH'],
        ['malaltia inflamat', 'bowel_movements', 'LOW'],
        ['gent gran', 'fluid_intake', 'HIGH'],
    ];

    // Sí/No: HIGH = "Sí" és el desitjable; LOW = "Sí" no és desitjable (símptoma). A LOW, alertValue = true.
    private const BOOLEAN_HIGH = ['fish_weekly', 'label_check', 'head_elevated', 'meds', 'medication', 'supplements', 'oral_supplements', 'olive_oil_main', 'olive_oil_amount', 'vegetables', 'fruit', 'red_meat', 'butter_cream', 'sugary_drink_low', 'legumes', 'fish', 'sweets', 'nuts', 'white_meat', 'sofrito', 'family_meals', 'coping'];
    private const BOOLEAN_LOW = ['cross_contact', 'blood_stool', 'ibd_complications', 'regurgitation', 'late_meal', 'nausea', 'sleep_disturbed', 'rescue_meds', 'hypo', 'ketones', 'nausea_vomiting', 'dumping', 'dizziness', 'swelling', 'mouth_sores', 'taste_change', 'hard_moment', 'binge_comp'];
    private const URGENT = ['blood_stool', 'hypo', 'ketones', 'ibd_complications'];
    private const REVIEW = ['cross_contact', 'regurgitation', 'late_meal', 'nausea', 'sleep_disturbed', 'rescue_meds', 'nausea_vomiting', 'dumping', 'dizziness', 'swelling', 'mouth_sores', 'taste_change'];
    // Camps sensibles (TCA): sense missatges numèrics ni alertes automàtiques.
    private const SENSITIVE = ['meal_time', 'place_company', 'emotion_before', 'hard_moment', 'binge_comp', 'context_notes', 'coping'];

    public function up(): void
    {
        $fields = DB::table('mst_library_routine_fields as f')
            ->join('mst_library_routines as r', 'r.id', '=', 'f.libraryRoutineId')
            ->get(['f.id', 'f.name', 'f.fieldType', 'f.frequency', 'f.goodDirection', 'r.category', 'r.name as routineName']);

        foreach ($fields as $field) {
            $update = [];
            $key = mb_strtolower($field->category.' | '.$field->routineName);

            if ($field->goodDirection === null) {
                if ($field->fieldType === 'NUMBER') {
                    $direction = self::NUMBER_DIRECTION[$field->name] ?? null;
                    foreach (self::NUMBER_OVERRIDES as [$needle, $name, $overrideDirection]) {
                        if ($name === $field->name && str_contains($key, $needle)) {
                            $direction = $overrideDirection;
                            break;
                        }
                    }
                    if ($direction) {
                        $update['goodDirection'] = $direction;
                    }
                } elseif ($field->fieldType === 'BOOLEAN') {
                    if (in_array($field->name, self::BOOLEAN_HIGH, true)) {
                        $update['goodDirection'] = 'HIGH';
                    } elseif (in_array($field->name, self::BOOLEAN_LOW, true)) {
                        $update['goodDirection'] = 'LOW';
                        $update['alertValue'] = true;
                    }
                }
            }

            if ($field->fieldType === 'BOOLEAN') {
                if (in_array($field->name, self::URGENT, true)) {
                    $update['alertLevel'] = 'URGENT';
                } elseif (in_array($field->name, self::REVIEW, true)) {
                    $update['alertLevel'] = 'REVIEW';
                }
            }
            if (in_array($field->name, self::SENSITIVE, true)) {
                $update['isSensitive'] = true;
            }
            // Els camps setmanals i els "quan hi ha símptoma" no obliguen a registrar cada dia.
            if (in_array($field->frequency, ['weekly', 'on_symptom'], true)) {
                $update['countsForAdherence'] = false;
            }

            if ($update !== []) {
                DB::table('mst_library_routine_fields')->where('id', $field->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        // Es desfà només el que aquesta migració ha pogut posar; les direccions que ja existien abans (escales) no es toquen.
        DB::table('mst_library_routine_fields')->whereIn('fieldType', ['NUMBER', 'BOOLEAN'])->update([
            'goodDirection' => null, 'alertValue' => null, 'alertLevel' => 'NONE',
        ]);
        DB::table('mst_library_routine_fields')->update(['isSensitive' => false, 'countsForAdherence' => true]);
    }
};
