<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

// Freqüències dels camps d'una plantilla: quins són setmanals i si TOTS ho són.
// Un camp setmanal es respon un cop per setmana (bloc de 7 dies des de l'inici de la rutina) i no
// compta com a "dia registrat" per a l'adherència. Es carrega d'un sol cop per petició (la taula és
// petita) perquè les llistes de pacients no facin una consulta per assignació.
class FieldFrequencies
{
    private static ?array $byTemplate = null;

    /**
     * @return array{weekly: string[], allWeekly: bool}
     */
    public static function forTemplate(string $templateId): array
    {
        if (self::$byTemplate === null) {
            self::$byTemplate = [];
            foreach (DB::table('mst_routine_fields')->get(['templateId', 'name', 'frequency']) as $field) {
                $entry = &self::$byTemplate[$field->templateId];
                $entry ??= ['weekly' => [], 'total' => 0];
                $entry['total']++;
                if ($field->frequency === 'weekly') {
                    $entry['weekly'][] = $field->name;
                }
                unset($entry);
            }
        }

        $entry = self::$byTemplate[$templateId] ?? ['weekly' => [], 'total' => 0];

        return [
            'weekly' => $entry['weekly'],
            'allWeekly' => $entry['total'] > 0 && count($entry['weekly']) === $entry['total'],
        ];
    }

    // Primer dia i últim dia (yyyy-mm-dd) del bloc de 7 dies de la rutina que conté $date.
    public static function weekRange(string $startDate, string $date): array
    {
        $start = \Carbon\CarbonImmutable::parse($startDate)->startOfDay();
        $day = \Carbon\CarbonImmutable::parse($date)->startOfDay();
        $index = intdiv(max(0, (int) $start->diffInDays($day)), 7);
        $from = $start->addDays($index * 7);

        return [$from->toDateString(), $from->addDays(6)->toDateString()];
    }
}
