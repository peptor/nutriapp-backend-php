<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

// Tendència simplificada d'una rutina per a la llista de pacients: compara els
// registres dels últims 7 dies (respecte al final efectiu de la rutina) amb els 7
// dies anteriors, per saber si el pacient hi ha estat més, igual o menys constant.
class AssignmentTrend
{
    /**
     * @param  Collection<int, \Carbon\Carbon|\Carbon\CarbonImmutable|string>  $recordDates
     * @return "progress"|"stable"|"declining"|"none"
     */
    public static function of(string|\DateTimeInterface $startDate, string|\DateTimeInterface $endDate, Collection $recordDates): string
    {
        $dates = $recordDates->map(fn ($date) => CarbonImmutable::parse($date)->startOfDay())->unique();

        if ($dates->isEmpty()) {
            return 'none';
        }

        $end = CarbonImmutable::parse($endDate)->startOfDay();
        $today = CarbonImmutable::now('UTC')->startOfDay();
        $effectiveEnd = $today->lt($end) ? $today : $end;

        $last7From = $effectiveEnd->subDays(6);
        $prev7To = $last7From->subDay();
        $prev7From = $prev7To->subDays(6);

        $lastCount = $dates->filter(fn ($d) => $d->gte($last7From) && $d->lte($effectiveEnd))->count();
        $prevCount = $dates->filter(fn ($d) => $d->gte($prev7From) && $d->lte($prev7To))->count();

        if ($lastCount > $prevCount) {
            return 'progress';
        }
        if ($lastCount < $prevCount) {
            return 'declining';
        }

        return 'stable';
    }
}
