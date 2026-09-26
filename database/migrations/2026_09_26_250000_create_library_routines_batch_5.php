<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Rutines noves de la biblioteca clínica (informe docs/informe-biblioteca-rutines.md, secció 6).
// Contingut clínic redactat com a ESBORRANY: veure docs/pendent-validacio-nutricionista.md. No porten
// aliments associats (pendent de definir). Les traduccions dels camps són a frontend/src/lib/libraryTexts.ts.
return new class extends Migration
{
    private const ROUTINES = [
        ['slug' => 'tca-registre-acompanyat', 'name' => 'TCA: registre acompanyat amb l\'equip clínic', 'description' => 'Registre d\'àpats, context i emocions per compartir amb l\'equip clínic que fa el tractament d\'un trastorn de la conducta alimentària. No inclou pes, calories ni objectius numèrics.', 'objective' => 'Ajudar a identificar amb l\'equip patrons, situacions i emocions relacionades amb el menjar. No és una rutina de control ni de dieta.', 'category' => 'TCA', 'tags' => 'TCA, Emocions, Context, Equip clínic', 'durationDays' => 28, 'caution' => 'Utilitza aquesta rutina només si la indica i la supervisa un equip clínic especialitzat en TCA; no substitueix el tractament. No hi afegeixis pes, calories ni quantitats d\'aliments, ni activis el registre d\'aliments. Si hi ha risc mèdic o psicològic, contacta amb l\'equip o amb urgències (112).', 'sourceName' => 'NICE NG69 — Eating disorders: recognition and treatment', 'sourceUrl' => 'https://www.nice.org.uk/guidance/ng69', 'clinicalSources' => [['name' => 'NICE NG69 — eating disorders', 'url' => 'https://www.nice.org.uk/guidance/ng69'], ['name' => 'Inside Out Institute — guide to self-monitoring (CBT-E)', 'url' => 'https://insideoutinstitute.org.au/resource-library/a-guide-to-self-monitoring']], 'icon' => 'lib-nutricionista', 'instructions' => [['Amb el teu equip', 'Aquest registre s\'ha de compartir i revisar amb el teu equip clínic; no és per fer-ho sol ni per controlar-te.'], ['Sense jutjar-te', 'Anota el que ha passat tal com ha estat, sense posar-hi nota. Res del que escriguis és bo o dolent: és informació per entendre\'n el context.'], ['Si et costa', 'Si el registre et genera malestar, deixa\'l i explica-ho al teu equip. Si estàs en perill o en crisi, truca al 112.']], 'fields' => [['meal_time', 'Àpat i hora', 'TEXT', 'daily', 0, 'Per exemple: dinar, a les 14:00.', null, null, 0, 10, null, 'document'], ['place_company', 'On i amb qui', 'TEXT', 'daily', 0, null, null, null, 0, 10, null, 'document'], ['emotion_before', 'Com et sentaves abans de menjar', 'TEXT', 'daily', 0, 'Escriu-ho amb les teves paraules; no cal que sigui exacte.', null, null, 0, 10, null, 'heart'], ['hard_moment', 'Un moment amb el menjar que ha sigut difícil', 'BOOLEAN', 'on_symptom', 0, 'Marca «Sí» si algun moment relacionat amb el menjar t\'ha resultat especialment difícil.', null, null, 0, 10, null, 'warning'], ['binge_comp', 'Episodi d\'afartament o de compensació', 'BOOLEAN', 'on_symptom', 0, 'Marca «Sí» només si en vols parlar amb el teu equip. No és per jutjar-te: és informació per ajudar-te.', null, null, 0, 10, null, 'warning'], ['context_notes', 'Què ha passat abans i després', 'TEXT', 'daily', 0, null, null, null, 0, 10, null, 'document'], ['coping', 'Has parlat amb algú o has fet servir alguna estratègia que t\'ajuda', 'BOOLEAN', 'daily', 0, null, null, null, 0, 10, null, 'heart']]],
    ];

    public function up(): void
    {
        foreach (self::ROUTINES as $r) {
            if (DB::table('mst_library_routines')->where('slug', $r['slug'])->exists()) {
                continue;
            }
            $id = (string) Str::uuid();
            DB::table('mst_library_routines')->insert([
                'id' => $id,
                'slug' => $r['slug'],
                'name' => $r['name'],
                'iconId' => DB::table('mst_fieldicons')->where('key', $r['icon'])->value('id'),
                'description' => $r['description'],
                'objective' => $r['objective'],
                'category' => $r['category'],
                'tags' => $r['tags'],
                'durationDays' => $r['durationDays'],
                'caution' => $r['caution'],
                'sourceName' => $r['sourceName'],
                'sourceUrl' => $r['sourceUrl'],
                'clinicalSources' => json_encode($r['clinicalSources'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'createdAt' => now(),
            ]);

            foreach ($r['instructions'] as $i => [$title, $content]) {
                DB::table('mst_library_routine_instructions')->insert([
                    'id' => (string) Str::uuid(), 'libraryRoutineId' => $id, 'title' => $title, 'content' => $content, 'orderIndex' => $i,
                ]);
            }

            foreach ($r['fields'] as $i => [$name, $label, $type, $freq, $required, $help, $options, $unit, $min, $max, $direction, $iconKey]) {
                DB::table('mst_library_routine_fields')->insert([
                    'id' => (string) Str::uuid(),
                    'libraryRoutineId' => $id,
                    'name' => $name,
                    'label' => $label,
                    'fieldType' => $type,
                    'fieldIconId' => DB::table('mst_fieldicons')->where('key', $iconKey)->value('id'),
                    'frequency' => $freq,
                    'required' => $required,
                    'options' => $options === null ? null : json_encode($options, JSON_UNESCAPED_UNICODE),
                    'orderIndex' => $i,
                    'helpText' => $help,
                    'scaleMin' => $type === 'SCALE' ? $min : 0,
                    'scaleMax' => $type === 'SCALE' ? $max : 10,
                    'goodDirection' => $type === 'SCALE' ? $direction : null,
                    'unit' => $unit,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::ROUTINES as $r) {
            $id = DB::table('mst_library_routines')->where('slug', $r['slug'])->value('id');
            if (! $id) {
                continue;
            }
            DB::table('mst_library_routine_fields')->where('libraryRoutineId', $id)->delete();
            DB::table('mst_library_routine_instructions')->where('libraryRoutineId', $id)->delete();
            DB::table('mst_library_routine_foods')->where('libraryRoutineId', $id)->delete();
            DB::table('mst_library_routines')->where('id', $id)->delete();
        }
    }
};
