<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

// Adherència: dies amb registre sobre els dies ja transcorreguts de la rutina.
// Completat: dies amb registre sobre el total de dies que dura la rutina (sencera).
// Només compten els camps amb `countsForAdherence` (vegeu FieldFrequencies::adherenceFor): els setmanals mai no
// són un dia registrat; si tots els que compten són setmanals es compten setmanes; si cap compta, no hi ha adherència.
class RoutineProgress
{
    // Adherència d'una assignació amb els registres carregats (calen recordDate i fieldName).
    public static function forAssignment($assignment): array
    {
        return self::of(
            $assignment->startDate,
            $assignment->endDate,
            self::countedDates($assignment),
            FieldFrequencies::adherenceFor($assignment->templateId)['mode']
        );
    }

    // Dates de registre que compten per a l'adherència i la tendència.
    public static function countedDates($assignment): Collection
    {
        $counted = FieldFrequencies::adherenceFor($assignment->templateId)['counted'];

        return $assignment->records->filter(fn ($record) => in_array($record->fieldName, $counted, true))->pluck('recordDate');
    }

    /**
     * @param  Collection<int, \Carbon\Carbon|\Carbon\CarbonImmutable|string>  $recordDates
     */
    public static function of(string|\DateTimeInterface $startDate, string|\DateTimeInterface $endDate, Collection $recordDates, string $unit = 'days'): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();
        $today = CarbonImmutable::now('UTC')->startOfDay();
        $effectiveEnd = $today->lt($end) ? $today : $end;
        // Cap camp compta per a l'adherència: no n'hi ha (0 %); la pantalla ho mostra sense dades.
        if ($unit === 'none') {
            $elapsed = max(1, $start->diffInDays($effectiveEnd) + 1);
            $total = max(1, $start->diffInDays($end) + 1);

            return ['adherencePercent' => 0, 'completedPercent' => 0, 'daysWithRecords' => 0, 'elapsedDays' => $elapsed, 'totalDays' => $total, 'progressUnit' => 'none'];
        }

        $elapsedDays = max(1, $start->diffInDays($effectiveEnd) + 1);
        $totalDays = max(1, $start->diffInDays($end) + 1);

        $daysWithRecords = $recordDates
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->unique()
            ->count();

        if ($unit === 'weeks') {
            // Setmanes amb algun registre sobre setmanes ja començades / setmanes totals.
            $elapsedDays = (int) ceil($elapsedDays / 7);
            $totalDays = (int) ceil($totalDays / 7);
            $daysWithRecords = $recordDates
                ->map(fn ($date) => intdiv(max(0, (int) $start->diffInDays(CarbonImmutable::parse($date)->startOfDay())), 7))
                ->unique()
                ->count();
        }

        return [
            'adherencePercent' => (int) round(($daysWithRecords / $elapsedDays) * 100),
            'completedPercent' => (int) round(($daysWithRecords / $totalDays) * 100),
            'daysWithRecords' => $daysWithRecords,
            'elapsedDays' => $elapsedDays,
            'totalDays' => $totalDays,
            'progressUnit' => $unit,
        ];
    }
}
