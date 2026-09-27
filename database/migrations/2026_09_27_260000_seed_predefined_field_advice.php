<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Consells predefinits per a camps clars de la biblioteca (docs/com-funcionen-les-alertes.md, "Consells").
// Redactats per la IA a partir del sentit de cada camp — proposta pendent de repassar (com la resta de contingut
// clínic de la biblioteca), no és una detecció automàtica ni necessita els mateixos llindars que les alertes.
// S'apliquen a `mst_library_field_advice` i, per als ja creats des d'aquesta rutina, també als seus
// `mst_routine_fields` (localitzats per `sourceLibraryFieldId`, ja establert per la migració 2026_09_27_150000).
//
// Els camps es localitzen per (nom de la rutina, nom del camp) — no per id: l'id d'un
// `mst_library_routine_fields` no és el mateix a cada entorn (es genera en sembrar la biblioteca), així que un id
// fixat aquí només funciona a l'entorn on es va escriure la migració (28/09/2026: va fallar a producció per
// aquest motiu, amb un id que només existia al dev local). Mateix criteri que 2026_09_26_130000.
return new class extends Migration
{
    private const ADVICE = [
        ['routine' => 'Celiaquia%', 'field' => 'symptoms', 'operator' => 'GTE', 'threshold' => 7, 'message' => 'Recorda anotar què has menjat durant les hores anteriors per poder revisar-ho a la propera visita.'],
        ['routine' => 'Nutrició esportiva%', 'field' => 'hydration', 'operator' => 'LT', 'threshold' => 1.5, 'message' => "Avui has registrat poca ingesta d'aigua. Recorda mantenir una hidratació adequada."],
        ['routine' => 'Reflux gastroesofàgic%', 'field' => 'heartburn', 'operator' => 'GTE', 'threshold' => 7, 'message' => "La cremor d'avui ha estat intensa. Evita ajeure't just després de menjar i revisa si hi ha algun aliment que et sol desencadenar-la."],
        ['routine' => '%restrenyiment%', 'field' => 'pain', 'operator' => 'GTE', 'threshold' => 7, 'message' => 'El dolor d\'avui ha estat alt. Si es repeteix els propers dies, comenta-ho amb el teu nutricionista.'],
        ['routine' => 'SII%', 'field' => 'bloating', 'operator' => 'GTE', 'threshold' => 7, 'message' => 'Avui has notat molta inflor. Anota quins aliments has provat recentment per repassar-ho a la propera revisió.'],
        ['routine' => 'Pediatria%', 'field' => 'sugary_drinks', 'operator' => 'GTE', 'threshold' => 2, 'message' => "Avui hi ha hagut més begudes ensucrades del compte. Prova d'oferir aigua com a primera opció."],
        ['routine' => 'Control de colesterol%', 'field' => 'healthy_fats', 'operator' => 'LT', 'threshold' => 1, 'message' => "Avui gairebé no has pres greixos saludables. Prova d'afegir-hi un raig d'oli d'oliva o un grapat de fruits secs."],
    ];

    private function libraryFieldId(string $routineNameLike, string $fieldName): ?string
    {
        $routineId = DB::table('mst_library_routines')->where('name', 'like', $routineNameLike)->value('id');
        if (! $routineId) {
            return null;
        }

        return DB::table('mst_library_routine_fields')->where('libraryRoutineId', $routineId)->where('name', $fieldName)->value('id');
    }

    public function up(): void
    {
        foreach (self::ADVICE as $rule) {
            $libraryFieldId = $this->libraryFieldId($rule['routine'], $rule['field']);
            if (! $libraryFieldId) {
                // Rutina o camp no sembrats en aquest entorn: se salta sense trencar el desplegament.
                continue;
            }
            if (DB::table('mst_library_field_advice')->where('libraryRoutineFieldId', $libraryFieldId)->exists()) {
                // Idempotent: a producció el primer intent (amb l'id fixat de l'entorn local, ja corregit més
                // amunt) va fallar a mig fer i va deixar 5 de les 7 regles ja inserides (28/09/2026). Sense
                // aquesta comprovació, tornar a desplegar les duplicaria en lloc de només afegir les que falten.
                continue;
            }

            $libraryAdviceId = (string) Str::uuid();
            DB::table('mst_library_field_advice')->insert([
                'id' => $libraryAdviceId,
                'libraryRoutineFieldId' => $libraryFieldId,
                'operator' => $rule['operator'],
                'thresholdValue' => $rule['threshold'],
                'message' => $rule['message'],
                'orderIndex' => 0,
                'createdAt' => now(),
            ]);

            $routineFieldIds = DB::table('mst_routine_fields')->where('sourceLibraryFieldId', $libraryFieldId)->pluck('id');
            foreach ($routineFieldIds as $routineFieldId) {
                DB::table('mst_routine_field_advice')->insert([
                    'id' => (string) Str::uuid(),
                    'routineFieldId' => $routineFieldId,
                    'operator' => $rule['operator'],
                    'thresholdValue' => $rule['threshold'],
                    'message' => $rule['message'],
                    'orderIndex' => 0,
                    'sourceLibraryAdviceId' => $libraryAdviceId,
                    'createdAt' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $libraryFieldIds = array_filter(array_map(fn (array $rule) => $this->libraryFieldId($rule['routine'], $rule['field']), self::ADVICE));

        DB::table('mst_routine_field_advice')->whereIn('sourceLibraryAdviceId', function ($q) use ($libraryFieldIds) {
            $q->select('id')->from('mst_library_field_advice')->whereIn('libraryRoutineFieldId', $libraryFieldIds);
        })->delete();
        DB::table('mst_library_field_advice')->whereIn('libraryRoutineFieldId', $libraryFieldIds)->delete();
    }
};
