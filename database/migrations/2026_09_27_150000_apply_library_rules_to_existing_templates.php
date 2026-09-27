<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Fase 1 (decisió 1 de docs/disseny-migracions-regles-camps.md): les plantilles ja existents que venen d'una
// rutina de la biblioteca reben les regles dels seus camps. Fins ara no guardaven d'on venien, així que se les
// identifica per descripció + categoria (una còpia manté ambdues, també si el nutricionista l'ha reanomenada) i
// els camps per nom + tipus. Només s'hi copien regles: no s'afegeixen ni s'eliminen camps, i la direcció bona
// que la plantilla ja tingués (escales) no es trepitja. Les plantilles creades des de zero no es toquen.
return new class extends Migration
{
    // Llista fixada aquí (i no importada de FieldRules) perquè una migració ja executada no canviï si el codi evoluciona.
    private const COLUMNS = [
        'alertMin', 'alertMax', 'alertValue', 'alertLevel', 'alertMinOccurrences', 'alertWindowDays', 'alertConsecutiveDays',
        'trendChangeAbs', 'trendChangePct', 'trendWindowDays', 'isKeyField', 'countsForAdherence', 'isSensitive', 'calculation',
    ];

    public function up(): void
    {
        $libraries = DB::table('mst_library_routines')->get(['id', 'description', 'category']);

        foreach (DB::table('mst_routine_templates')->get(['id', 'description', 'category']) as $template) {
            if ($template->description === null || $template->description === '') {
                continue;
            }
            $matches = $libraries->filter(fn ($library) => $library->description === $template->description && $library->category === $template->category);
            if ($matches->count() !== 1) {
                continue; // cap coincidència o ambigua: no es toca
            }

            $libraryFields = DB::table('mst_library_routine_fields')->where('libraryRoutineId', $matches->first()->id)->get()->keyBy(fn ($f) => $f->name.'|'.$f->fieldType);

            foreach (DB::table('mst_routine_fields')->where('templateId', $template->id)->whereNull('sourceLibraryFieldId')->get() as $field) {
                $source = $libraryFields[$field->name.'|'.$field->fieldType] ?? null;
                if (! $source) {
                    continue;
                }

                $update = ['sourceLibraryFieldId' => $source->id];
                foreach (self::COLUMNS as $column) {
                    $update[$column] = $source->{$column};
                }
                if ($field->goodDirection === null) {
                    $update['goodDirection'] = $source->goodDirection;
                }

                DB::table('mst_routine_fields')->where('id', $field->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        // Millor esforç: torna a l'estat per defecte les regles dels camps que tenen origen a la biblioteca
        // (inclosos els de plantilles creades després d'aquesta migració).
        DB::table('mst_routine_fields')->whereNotNull('sourceLibraryFieldId')->update([
            'alertMin' => null, 'alertMax' => null, 'alertValue' => null, 'alertLevel' => 'NONE', 'alertMinOccurrences' => null,
            'alertWindowDays' => null, 'alertConsecutiveDays' => null, 'trendChangeAbs' => null, 'trendChangePct' => null,
            'trendWindowDays' => 7, 'isKeyField' => false, 'countsForAdherence' => true, 'isSensitive' => false, 'calculation' => null,
            'sourceLibraryFieldId' => null,
        ]);
    }
};
