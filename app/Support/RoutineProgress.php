<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

// Adherència: dies amb registre sobre els dies ja transcorreguts de la rutina.
// Completat: dies amb registre sobre el total de dies que dura la rutina (sencera).
// Els registres de camps setmanals no compten com a dia registrat; si TOTS els camps de la rutina
// són setmanals, es compten setmanes (blocs de 7 dies des de l'inici) en lloc de dies.
class RoutineProgress
{
    // Adherència d'una assignació amb els registres carregats (calen recordDate i fieldName).
    public static function forAssignment($assignment): array
    {
        $frequencies = FieldFrequencies::forTemplate($assignment->templateId);

        return self::of(
            $assignment->startDate,
            $assignment->endDate,
            self::countedDates($assignment),
            $frequencies['allWeekly'] ? 'weeks' : 'days'
        );
    }

    // Dates de registre que compten per a l'adherència i la tendència.
    public static function countedDates($assignment): Collection
    {
        $frequencies = FieldFrequencies::forTemplate($assignment->templateId);
        $records = $assignment->records;

        return ($frequencies['allWeekly']
            ? $records
            : $records->reject(fn ($record) => in_array($record->fieldName, $frequencies['weekly'], true))
        )->pluck('recordDate');
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
