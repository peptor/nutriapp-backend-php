<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Amplia RGE, SII, diabetis, renal i bariàtrica amb els ítems validats que els faltaven segons
// docs/informe-biblioteca-rutines.md (punt 2 de l'ordre de treball). Tots els camps nous són OPCIONALS
// i s'afegeixen al final de la rutina. Només afecta les plantilles noves creades des de la biblioteca.
// Contingut clínic redactat com a esborrany: veure docs/pendent-validacio-nutricionista.md.
// Les traduccions dels textos són a frontend/src/lib/libraryTexts.ts (coincidència exacta).
return new class extends Migration
{
    // [patró de la rutina, nom, etiqueta, tipus, freqüència, ajuda, opcions, unitat, scaleMin, scaleMax, sentit, icona]
    private const FIELDS = [
            ['Reflux gastroesofàgic%', 'epigastric_pain', 'Dolor a la boca de l\'estómac (0-10)', 'SCALE', 'daily', '0 = cap dolor; 10 = dolor molt intens a la part alta de l\'abdomen, sota l\'estèrnum.', null, null, 0, 10, 'LOW', 'stomach'],
            ['Reflux gastroesofàgic%', 'nausea', 'Nàusees', 'BOOLEAN', 'on_symptom', null, null, null, 0, 10, null, 'stomach'],
            ['Reflux gastroesofàgic%', 'sleep_disturbed', 'Alteració del son per reflux', 'BOOLEAN', 'on_symptom', 'Marca «Sí» si el reflux t\'ha despertat o t\'ha impedit dormir bé.', null, null, 0, 10, null, 'warning'],
            ['Reflux gastroesofàgic%', 'rescue_meds', 'Ús d\'antiàcids o medicació de rescat', 'BOOLEAN', 'on_symptom', 'Marca «Sí» si has pres algun antiàcid o medicament de rescat fora de la pauta fixa.', null, null, 0, 10, null, 'document'],
            ['Reflux gastroesofàgic%', 'heartburn_days', 'Dies d\'aquesta setmana amb cremor o regurgitació', 'NUMBER', 'weekly', 'Compta els dies (de 0 a 7) d\'aquesta setmana amb cremor o regurgitació.', null, 'dies', 0, 10, null, 'stomach'],
            ['Reflux gastroesofàgic%', 'weight', 'Pes', 'NUMBER', 'weekly', 'Pesa\'t sempre en les mateixes condicions, per exemple al matí, en dejú i després d\'anar al lavabo.', null, 'kg', 0, 10, null, 'heart'],
            ['SII%', 'interference', 'Interferència amb la vida diària (0-10)', 'SCALE', 'daily', '0 = els símptomes no t\'han afectat gens; 10 = t\'han impedit fer vida normal.', null, null, 0, 10, 'LOW', 'heart'],
            ['SII%', 'pain_days', 'Dies d\'aquesta setmana amb dolor abdominal', 'NUMBER', 'weekly', 'Compta els dies (de 0 a 7) d\'aquesta setmana amb dolor abdominal.', null, 'dies', 0, 10, null, 'stomach'],
            ['SII%', 'bowel_satisfaction', 'Satisfacció amb el ritme intestinal (0-10)', 'SCALE', 'weekly', '0 = gens de satisfacció; 10 = satisfacció total amb el ritme intestinal d\'aquesta setmana.', null, null, 0, 10, 'HIGH', 'heart'],
            ['SII%', 'fluid_cups', 'Líquids', 'NUMBER', 'daily', 'Suma les tasses de líquid del dia (aigua, infusions...). La pauta general és almenys 8 tasses (uns 2 L).', null, 'tasses', 0, 10, null, 'leaf'],
            ['SII%', 'caffeine_cups', 'Te o cafè', 'NUMBER', 'daily', 'Tasses de te o cafè del dia. La pauta general és no passar de 3 al dia.', null, 'tasses', 0, 10, null, 'leaf'],
            ['SII%', 'fodmap_group', 'Grup FODMAP que estàs provant', 'SELECT', 'daily', 'Durant la reintroducció, tria el grup que estàs provant. En l\'eliminació deixa «Cap».', ['Cap (fase d\'eliminació)', 'Lactosa', 'Fructosa', 'Fructans', 'GOS (galactooligosacàrids)', 'Sorbitol', 'Manitol'], null, 0, 10, null, 'utensils'],
            ['Diabetis%', 'glucose_fasting', 'Glucosa en dejú (si està indicada)', 'NUMBER', 'daily', 'Valor del glucòmetre en mg/dL en llevar-te, abans d\'esmorzar. Només si el teu equip t\'ho ha indicat.', null, 'mg/dL', 0, 10, null, 'heart'],
            ['Diabetis%', 'glucose_bedtime', 'Glucosa en anar a dormir (si està indicada)', 'NUMBER', 'daily', 'Valor del glucòmetre en mg/dL abans d\'anar a dormir. Només si el teu equip t\'ho ha indicat.', null, 'mg/dL', 0, 10, null, 'heart'],
            ['Diabetis%', 'activity_minutes', 'Activitat física', 'NUMBER', 'daily', 'Suma els minuts d\'activitat física del dia, com caminar a bon ritme, anar en bicicleta o fer esport.', null, 'min', 0, 10, null, 'running'],
            ['Diabetis%', 'medication_note', 'Dosi i hora de la medicació (si escau)', 'TEXT', 'daily', 'Anota la dosi i l\'hora si prens insulina o antidiabètics, tal com t\'ho ha pautat el teu equip.', null, null, 0, 10, null, 'document'],
            ['Diabetis%', 'weight', 'Pes', 'NUMBER', 'weekly', 'Pesa\'t sempre en les mateixes condicions, per exemple al matí, en dejú i després d\'anar al lavabo.', null, 'kg', 0, 10, null, 'heart'],
            ['Diabetis%', 'time_in_range', 'Temps en rang 70-180 mg/dL (només si portes sensor)', 'NUMBER', 'weekly', 'Percentatge de temps de la setmana amb la glucosa entre 70 i 180 mg/dL, segons l\'informe del sensor (CGM). L\'objectiu habitual és superar el 70 %.', null, '%', 0, 10, null, 'heart'],
            ['Malaltia renal crònica%', 'weight', 'Pes', 'NUMBER', 'daily', 'Pesa\'t sempre en les mateixes condicions, per exemple al matí, en dejú i després d\'anar al lavabo.', null, 'kg', 0, 10, null, 'heart'],
            ['Malaltia renal crònica%', 'potassium_lab', 'Potassi sèric (última analítica)', 'NUMBER', 'weekly', 'Només quan tinguis una analítica nova: anota el valor de potassi que hi consta.', null, 'mmol/L', 0, 10, null, 'document'],
            ['Malaltia renal crònica%', 'phosphorus_lab', 'Fòsfor sèric (última analítica)', 'NUMBER', 'weekly', 'Només quan tinguis una analítica nova: anota el valor de fòsfor que hi consta.', null, 'mg/dL', 0, 10, null, 'document'],
            ['Postoperatori%', 'dumping', 'Símptomes de dumping', 'BOOLEAN', 'on_symptom', 'Marca «Sí» si després de menjar has notat suor, palpitacions, mareig, rampes o diarrea.', null, null, 0, 10, null, 'warning'],
            ['Postoperatori%', 'dizziness', 'Mareig', 'BOOLEAN', 'on_symptom', null, null, null, 0, 10, null, 'warning'],
            ['Postoperatori%', 'vomiting_count', 'Vòmits (nombre)', 'NUMBER', 'on_symptom', null, null, null, 0, 10, null, 'stomach'],
            ['Postoperatori%', 'weight', 'Pes', 'NUMBER', 'weekly', 'Pesa\'t sempre en les mateixes condicions, per exemple al matí, en dejú i després d\'anar al lavabo.', null, 'kg', 0, 10, null, 'heart'],
            ['Postoperatori%', 'bowel_movements', 'Deposicions (número)', 'NUMBER', 'daily', 'Nombre de vegades que has anat de ventre durant el dia.', null, null, 0, 10, null, 'stomach'],
    ];

    // Durada de les rutines que canvia: [patró, dies nous, dies anteriors]
    private const DURATIONS = [
        ['Postoperatori%', 84, 30],
    ];

    public function up(): void
    {
        $orders = [];
        foreach (self::FIELDS as [$pattern, $name, $label, $type, $freq, $help, $options, $unit, $min, $max, $direction, $iconKey]) {
            $routineId = DB::table('mst_library_routines')->where('name', 'like', $pattern)->value('id');
            if (! $routineId) {
                continue;
            }
            if (DB::table('mst_library_routine_fields')->where('libraryRoutineId', $routineId)->where('name', $name)->exists()) {
                continue;
            }
            $orders[$routineId] ??= (int) DB::table('mst_library_routine_fields')->where('libraryRoutineId', $routineId)->max('orderIndex');
            $orders[$routineId]++;

            DB::table('mst_library_routine_fields')->insert([
                'id' => (string) Str::uuid(),
                'libraryRoutineId' => $routineId,
                'name' => $name,
                'label' => $label,
                'fieldType' => $type,
                'fieldIconId' => DB::table('mst_fieldicons')->where('key', $iconKey)->value('id'),
                'frequency' => $freq,
                'required' => 0,
                'options' => $options === null ? null : json_encode($options, JSON_UNESCAPED_UNICODE),
                'orderIndex' => $orders[$routineId],
                'helpText' => $help,
                'scaleMin' => $type === 'SCALE' ? $min : 0,
                'scaleMax' => $type === 'SCALE' ? $max : 10,
                'goodDirection' => $type === 'SCALE' ? $direction : null,
                'unit' => $unit,
            ]);
        }

        foreach (self::DURATIONS as [$pattern, $days]) {
            DB::table('mst_library_routines')->where('name', 'like', $pattern)->update(['durationDays' => $days]);
        }
    }

    public function down(): void
    {
        foreach (self::FIELDS as [$pattern, $name]) {
            $routineId = DB::table('mst_library_routines')->where('name', 'like', $pattern)->value('id');
            if ($routineId) {
                DB::table('mst_library_routine_fields')->where('libraryRoutineId', $routineId)->where('name', $name)->delete();
            }
        }

        foreach (self::DURATIONS as [$pattern, , $previous]) {
            DB::table('mst_library_routines')->where('name', 'like', $pattern)->update(['durationDays' => $previous]);
        }
    }
};
