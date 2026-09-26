<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Corregeix camps de la biblioteca de rutines que tenien un tipus que no els corresponia:
// - Pressió arterial (DASH i renal): TEXT -> BLOOD_PRESSURE, i opcional ("si està indicada").
// - Fase de textura (bariàtrica): TEXT -> SELECT amb les 5 fases de la progressió.
// - Bristol (restrenyiment i SII): el tipus SCALE és sempre 0-10 i acceptaria valors invàlids;
//   passa a SELECT amb els 7 tipus. Al SII, "Deposicions i Bristol" (text) es divideix en
//   deposicions (NUMBER) + Bristol (SELECT).
// Només afecta les plantilles noves creades des de la biblioteca (les existents tenen la seva còpia).
return new class extends Migration
{
    private const PHASE_OPTIONS = ['Líquids clars', 'Líquids complets', 'Triturat', 'Tou', 'Normal'];

    private const BRISTOL_OPTIONS = [
        '1 · Boletes dures separades',
        '2 · Forma de botifarra, grumollosa',
        '3 · Forma de botifarra, amb esquerdes',
        '4 · Forma de botifarra, llisa i suau',
        '5 · Trossos tous amb vores definides',
        '6 · Trossos pastosos amb vores desfetes',
        '7 · Aquosa, sense trossos sòlids',
    ];

    private function routineId(string $like): ?string
    {
        return DB::table('mst_library_routines')->where('name', 'like', $like)->value('id');
    }

    private function json(array $values): string
    {
        return json_encode($values, JSON_UNESCAPED_UNICODE);
    }

    public function up(): void
    {
        // 1. Pressió arterial: DASH i renal.
        DB::table('mst_library_routine_fields')
            ->where('name', 'bp')
            ->where('fieldType', 'TEXT')
            ->update(['fieldType' => 'BLOOD_PRESSURE', 'required' => 0]);

        // 2. Fase de textura de bariàtrica.
        DB::table('mst_library_routine_fields')
            ->where('name', 'phase')
            ->where('fieldType', 'TEXT')
            ->update([
                'fieldType' => 'SELECT',
                'label' => 'Fase de textura',
                'options' => $this->json(self::PHASE_OPTIONS),
            ]);

        // 3. Bristol a restrenyiment: SCALE -> SELECT.
        DB::table('mst_library_routine_fields')
            ->where('name', 'bristol')
            ->where('fieldType', 'SCALE')
            ->update([
                'fieldType' => 'SELECT',
                'label' => 'Tipus de femta (escala de Bristol)',
                'options' => $this->json(self::BRISTOL_OPTIONS),
            ]);

        // 4. SII: "Deposicions i Bristol" -> deposicions (NUMBER) + Bristol (SELECT).
        $siiId = $this->routineId('SII%');
        $stool = $siiId
            ? DB::table('mst_library_routine_fields')->where('libraryRoutineId', $siiId)->where('name', 'stool')->first()
            : null;

        if ($stool) {
            DB::table('mst_library_routine_fields')
                ->where('libraryRoutineId', $siiId)
                ->where('orderIndex', '>', $stool->orderIndex)
                ->increment('orderIndex');

            DB::table('mst_library_routine_fields')->where('id', $stool->id)->update([
                'name' => 'bowel_movements',
                'label' => 'Deposicions (número)',
                'fieldType' => 'NUMBER',
            ]);

            DB::table('mst_library_routine_fields')->insert([
                'id' => (string) Str::uuid(),
                'libraryRoutineId' => $siiId,
                'name' => 'bristol',
                'label' => 'Tipus de femta (escala de Bristol)',
                'fieldType' => 'SELECT',
                'fieldIconId' => $stool->fieldIconId,
                'frequency' => $stool->frequency,
                'required' => $stool->required,
                'options' => $this->json(self::BRISTOL_OPTIONS),
                'orderIndex' => $stool->orderIndex + 1,
                'helpText' => null,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('mst_library_routine_fields')
            ->where('name', 'bp')
            ->where('fieldType', 'BLOOD_PRESSURE')
            ->update(['fieldType' => 'TEXT', 'required' => 1]);

        DB::table('mst_library_routine_fields')
            ->where('name', 'phase')
            ->where('fieldType', 'SELECT')
            ->update([
                'fieldType' => 'TEXT',
                'label' => 'Fase de textura (líquids/triturats/tous/normal)',
                'options' => null,
            ]);

        $siiId = $this->routineId('SII%');
        if ($siiId) {
            $bristol = DB::table('mst_library_routine_fields')->where('libraryRoutineId', $siiId)->where('name', 'bristol')->first();
            if ($bristol) {
                DB::table('mst_library_routine_fields')->where('id', $bristol->id)->delete();
                DB::table('mst_library_routine_fields')
                    ->where('libraryRoutineId', $siiId)
                    ->where('orderIndex', '>', $bristol->orderIndex)
                    ->decrement('orderIndex');
                DB::table('mst_library_routine_fields')
                    ->where('libraryRoutineId', $siiId)
                    ->where('name', 'bowel_movements')
                    ->update(['name' => 'stool', 'label' => 'Deposicions i Bristol (1-7)', 'fieldType' => 'TEXT']);
            }
        }

        DB::table('mst_library_routine_fields')
            ->where('name', 'bristol')
            ->where('fieldType', 'SELECT')
            ->update([
                'fieldType' => 'SCALE',
                'label' => 'Escala de Bristol (1-7)',
                'options' => null,
            ]);
    }
};
