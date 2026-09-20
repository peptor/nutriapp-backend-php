<?php

namespace App\Support;

use App\Models\NutricionistaProfile;
use App\Models\ScheduleException;
use App\Models\ScheduleSlot;
use Carbon\CarbonImmutable;

// Calcula, per a qualsevol data, si el nutricionista treballa i amb quins trams horaris,
// combinant el patró habitual (setmana única o A/B alternades) amb les excepcions puntuals
// (festius, absències, vacances o horari especial), que sempre tenen prioritat sobre el patró.
class ScheduleResolver
{
    /**
     * @return array<int, array{date:string, working:bool, type:string, ranges:array<int,array{start:string,end:string}>, description:?string, week:?string}>
     */
    public static function resolveRange(string $nutricionistaId, string $from, string $to): array
    {
        $profile = NutricionistaProfile::where('userId', $nutricionistaId)->first();
        $mode = $profile->scheduleMode ?? 'WEEKLY';
        $cycleStart = $profile?->scheduleCycleStartDate;
        $cycleStartWeek = $profile->scheduleCycleStartWeek ?? 'A';

        $slotsByWeekDay = [];
        foreach (ScheduleSlot::where('nutricionistaId', $nutricionistaId)->get() as $slot) {
            $key = ($slot->week ?? 'X').'-'.$slot->dayOfWeek;
            $slotsByWeekDay[$key] ??= [];
            $slotsByWeekDay[$key][] = ['start' => substr($slot->startTime, 0, 5), 'end' => substr($slot->endTime, 0, 5)];
        }

        $exceptions = ScheduleException::where('nutricionistaId', $nutricionistaId)
            ->whereBetween('date', [$from, $to])
            ->get()
            ->keyBy(fn ($e) => $e->date->format('Y-m-d'));

        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);
        $results = [];

        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $dateStr = $d->format('Y-m-d');
            $exception = $exceptions->get($dateStr);
            $week = self::weekLetterFor($mode, $cycleStart, $cycleStartWeek, $d);

            if ($exception) {
                $isSpecial = $exception->type === 'HORARI_ESPECIAL';
                $results[] = [
                    'date' => $dateStr,
                    'working' => $isSpecial,
                    'type' => $exception->type,
                    'ranges' => $isSpecial ? [['start' => substr($exception->startTime, 0, 5), 'end' => substr($exception->endTime, 0, 5)]] : [],
                    'description' => $exception->description,
                    'week' => $week,
                ];

                continue;
            }

            $dayOfWeek = $d->dayOfWeekIso - 1; // Carbon: 1=dilluns..7=diumenge -> 0..6
            $key = ($week ?? 'X').'-'.$dayOfWeek;

            $results[] = [
                'date' => $dateStr,
                'working' => count($slotsByWeekDay[$key] ?? []) > 0,
                'type' => 'NORMAL',
                'ranges' => $slotsByWeekDay[$key] ?? [],
                'description' => null,
                'week' => $week,
            ];
        }

        return $results;
    }

    private static function weekLetterFor(string $mode, ?string $cycleStart, string $cycleStartWeek, CarbonImmutable $date): ?string
    {
        if ($mode !== 'BIWEEKLY') {
            return null;
        }
        if (! $cycleStart) {
            return $cycleStartWeek;
        }

        $cycleStartOfWeek = CarbonImmutable::parse($cycleStart)->startOfWeek(CarbonImmutable::MONDAY);
        $dateStartOfWeek = $date->startOfWeek(CarbonImmutable::MONDAY);
        // Ambdues són sempre dilluns, així que la diferència en dies és múltiple exacte de 7.
        $diffDays = (int) round(($dateStartOfWeek->getTimestamp() - $cycleStartOfWeek->getTimestamp()) / 86400);
        $weeksBetween = intdiv($diffDays, 7);
        $parity = (($weeksBetween % 2) + 2) % 2;
        $letters = $cycleStartWeek === 'A' ? ['A', 'B'] : ['B', 'A'];

        return $letters[$parity];
    }
}
