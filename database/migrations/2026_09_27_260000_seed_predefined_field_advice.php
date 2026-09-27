<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Consells predefinits per a camps clars de la biblioteca (docs/com-funcionen-les-alertes.md, "Consells").
// Redactats per la IA a partir del sentit de cada camp — proposta pendent de repassar (com la resta de contingut
// clínic de la biblioteca), no és una detecció automàtica ni necessita els mateixos llindars que les alertes.
// S'apliquen a `mst_library_field_advice` i, per als ja creats des d'aquesta rutina, també als seus
// `mst_routine_fields` (localitzats per `sourceLibraryFieldId`, ja establert per la migració 2026_09_27_150000).
return new class extends Migration
{
    private const ADVICE = [
        // libraryRoutineFieldId => [operator, thresholdValue, message]
        '5884ec0e-32d6-46f6-82b5-495900bac9f3' => ['GTE', 7, 'Recorda anotar què has menjat durant les hores anteriors per poder revisar-ho a la propera visita.'], // Celiaquia · symptoms
        '040f1a5e-9dc1-4d34-ae3a-e090fe40c60b' => ['LT', 1.5, "Avui has registrat poca ingesta d'aigua. Recorda mantenir una hidratació adequada."], // Esportiva · hydration
        'cfc490e8-a9e4-4624-9a3e-fddd40bc7a4a' => ['GTE', 7, "La cremor d'avui ha estat intensa. Evita ajeure't just després de menjar i revisa si hi ha algun aliment que et sol desencadenar-la."], // RGE · heartburn
        '45898388-70af-4612-b4cb-fb9bbf84d8ee' => ['GTE', 7, 'El dolor d\'avui ha estat alt. Si es repeteix els propers dies, comenta-ho amb el teu nutricionista.'], // Restrenyiment · pain
        'f0f15859-f122-4613-be26-fb623ad112bb' => ['GTE', 7, 'Avui has notat molta inflor. Anota quins aliments has provat recentment per repassar-ho a la propera revisió.'], // SII · bloating
        'ffb3a0d8-0295-470c-b74c-9f282c5b7fea' => ['GTE', 2, "Avui hi ha hagut més begudes ensucrades del compte. Prova d'oferir aigua com a primera opció."], // Pediatria · sugary_drinks
        '621bb0f3-1fd2-45ad-a08a-c07e6869f0bd' => ['LT', 1, "Avui gairebé no has pres greixos saludables. Prova d'afegir-hi un raig d'oli d'oliva o un grapat de fruits secs."], // Colesterol · healthy_fats
    ];

    public function up(): void
    {
        foreach (self::ADVICE as $libraryFieldId => [$operator, $threshold, $message]) {
            $libraryAdviceId = (string) Str::uuid();
            DB::table('mst_library_field_advice')->insert([
                'id' => $libraryAdviceId,
                'libraryRoutineFieldId' => $libraryFieldId,
                'operator' => $operator,
                'thresholdValue' => $threshold,
                'message' => $message,
                'orderIndex' => 0,
                'createdAt' => now(),
            ]);

            $routineFieldIds = DB::table('mst_routine_fields')->where('sourceLibraryFieldId', $libraryFieldId)->pluck('id');
            foreach ($routineFieldIds as $routineFieldId) {
                DB::table('mst_routine_field_advice')->insert([
                    'id' => (string) Str::uuid(),
                    'routineFieldId' => $routineFieldId,
                    'operator' => $operator,
                    'thresholdValue' => $threshold,
                    'message' => $message,
                    'orderIndex' => 0,
                    'sourceLibraryAdviceId' => $libraryAdviceId,
                    'createdAt' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('mst_routine_field_advice')->whereIn('sourceLibraryAdviceId', function ($q) {
            $q->select('id')->from('mst_library_field_advice')->whereIn('libraryRoutineFieldId', array_keys(self::ADVICE));
        })->delete();
        DB::table('mst_library_field_advice')->whereIn('libraryRoutineFieldId', array_keys(self::ADVICE))->delete();
    }
};
