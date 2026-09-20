<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Assignació manual de les 8 icones genèriques als camps de les rutines de biblioteca
    // (predefinides), agrupats per significat (símptomes digestius, activitat física,
    // aliments, alertes/eleccions a limitar, constants vitals, text lliure...).
    public function up(): void
    {
        $iconIdByKey = DB::table('mst_fieldicons')->pluck('id', 'key');

        $assignments = [
            'utensils' => [
                'gluten_free_meals', 'healthy_fats', 'fish_weekly', 'plate', 'carb_portion',
                'protein_portions', 'pre_workout_meal', 'post_workout_meal', 'meals',
                'protein_intake', 'late_meal', 'meal',
            ],
            'stomach' => [
                'symptoms', 'hunger', 'tolerance', 'nausea_vomiting', 'heartburn', 'regurgitation',
                'bowel_movements', 'bristol', 'straining', 'pain', 'bloating', 'stool',
            ],
            'warning' => [
                'cross_contact', 'saturated_fat_choices', 'hypo', 'salt_choices', 'swelling',
                'processed_food', 'sodium_choices',
            ],
            'heart' => [
                'glucose_before', 'glucose_after', 'bp', 'weight', 'sleep_hours', 'stress',
            ],
            'apple' => [
                'fruit_veg', 'whole_grains', 'fruit_vegetables',
            ],
            'leaf' => [
                'fiber_portions', 'fluid_intake', 'hydration', 'water', 'fiber',
            ],
            'running' => [
                'activity_minutes', 'training_minutes', 'perceived_effort', 'activity',
            ],
            'document' => [
                'label_check', 'notes', 'medication', 'labs_note', 'phase', 'supplements',
                'trigger_food', 'head_elevated', 'meds', 'suspected_trigger',
            ],
        ];

        foreach ($assignments as $iconKey => $names) {
            if (! isset($iconIdByKey[$iconKey])) {
                continue;
            }
            DB::table('mst_library_routine_fields')->whereIn('name', $names)->update([
                'fieldIconId' => $iconIdByKey[$iconKey],
            ]);
        }
    }

    public function down(): void
    {
        // No es desfà: assignar una icona no és una operació destructiva a revertir.
    }
};
