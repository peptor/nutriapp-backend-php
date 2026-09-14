<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

// Adherència: dies amb registre sobre els dies ja transcorreguts de la rutina.
// Completat: dies amb registre sobre el total de dies que dura la rutina (sencera).
class RoutineProgress
{
    /**
     * @param  Collection<int, \Carbon\Carbon|\Carbon\CarbonImmutable|string>  $recordDates
     */
    public static function of(string|\DateTimeInterface $startDate, string|\DateTimeInterface $endDate, Collection $recordDates): array
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

        return [
            'adherencePercent' => (int) round(($daysWithRecords / $elapsedDays) * 100),
            'completedPercent' => (int) round(($daysWithRecords / $totalDays) * 100),
            'daysWithRecords' => $daysWithRecords,
            'elapsedDays' => $elapsedDays,
            'totalDays' => $totalDays,
        ];
    }
}
