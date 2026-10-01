<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

// Progrés clínic d'una rutina: combina el RITME de registre (App\Support\AssignmentTrend, últims 7 dies contra
// els 7 anteriors) amb com EVOLUCIONEN ELS VALORS dels camps clau (`isKeyField`) de la rutina, en el mateix
// període. Decisions (27/09/2026):
// - Només compten els camps marcats com a clau. Si n'hi ha més d'un, es fa la MITJANA de les seves tendències
//   qualificades (favorable = +1, desfavorable = -1; un camp sense tendència prou significativa NO compta en
//   la mitjana). Mitjana > 0 → "valors bé"; < 0 → "valors malament"; 0 exacte o cap camp avaluable → no es pot
//   dir, es manté només el ritme.
// - Amb ritme "en progrés" o "estable" i valors "malament", surt un cinquè estat nou: `needs_attention`
//   ("Registra bé, però cal revisar els valors"). El ritme "baixant" es manté tal qual (la irregularitat ja és
//   el problema principal), independentment dels valors.
//
// Mateix càlcul de tendència per camp que el frontend (frontend/src/components/VariablesSummaryCard.tsx,
// `significantTrend`/`evolutionOf`/`qualifiedTrend`): mitjana dels últims 7 dies contra la dels 7 anteriors.
// NUMBER i BOOLEAN només compten si el canvi supera `trendChangeAbs`/`trendChangePct` (si n'hi ha); SCALE i
// BLOOD_PRESSURE no tenen llindar, qualsevol canvi compta. Els camps SELECT/TEXT/MEAL, o amb `goodDirection`
// buit o `TARGET`, no es poden avaluar i no compten a la mitjana.
class ClinicalProgress
{
    /**
     * @param  Collection<int,string>  $countedRecordDates  Mateix input que AssignmentTrend::of (dies que compten per a l'adherència).
     * @param  Collection  $records  Tots els registres de l'assignació (recordDate, fieldName, value).
     * @param  Collection  $keyFields  Camps amb isKeyField=true (name, fieldType, goodDirection, trendChangeAbs, trendChangePct).
     * @return "progress"|"stable"|"declining"|"needs_attention"|"none"
     */
    public static function of(string|\DateTimeInterface $startDate, string|\DateTimeInterface $endDate, Collection $countedRecordDates, Collection $records, Collection $keyFields): string
    {
        $rhythm = AssignmentTrend::of($startDate, $endDate, $countedRecordDates);
        if ($rhythm === 'none' || $keyFields->isEmpty()) {
            return $rhythm;
        }

        $verdict = self::valuesVerdict($endDate, $records, $keyFields);
        if ($verdict === null) {
            return $rhythm;
        }
        if (in_array($rhythm, ['progress', 'stable'], true) && $verdict === 'bad') {
            return 'needs_attention';
        }

        return $rhythm;
    }

    // "good" | "bad" | null (cap camp clau avaluable, o empat exacte).
    private static function valuesVerdict(string|\DateTimeInterface $endDate, Collection $records, Collection $keyFields): ?string
    {
        $end = Carbon::parse($endDate)->startOfDay();
        $today = Carbon::now('UTC')->startOfDay();
        $effectiveEnd = $today->lt($end) ? $today : $end;
        $last7From = $effectiveEnd->copy()->subDays(6);
        $prev7To = $last7From->copy()->subDay();
        $prev7From = $prev7To->copy()->subDays(6);

        $scores = [];
        foreach ($keyFields as $field) {
            $score = self::fieldScore($field, $records, $last7From, $effectiveEnd, $prev7From, $prev7To);
            if ($score !== null) {
                $scores[] = $score;
            }
        }
        if ($scores === []) {
            return null;
        }
        $avg = array_sum($scores) / count($scores);
        if ($avg > 0) {
            return 'good';
        }
        if ($avg < 0) {
            return 'bad';
        }

        return null;
    }

    // +1 favorable, -1 desfavorable, null si no s'hi pot avaluar (tipus sense tendència, sense direcció, TARGET,
    // canvi no significatiu, o menys de dos períodes amb dades). Públic: també el fa servir
    // App\Support\AlertGenerator per a les alertes TREND_WORSE (mateix càlcul, camp a camp).
    public static function fieldScore($field, Collection $records, Carbon $last7From, Carbon $last7To, Carbon $prev7From, Carbon $prev7To): ?int
    {
        $fieldType = $field->fieldType ?? $field['fieldType'] ?? null;
        $direction = $field->goodDirection ?? $field['goodDirection'] ?? null;
        $name = $field->name ?? $field['name'] ?? null;
        if (! $name || ! in_array($fieldType, ['NUMBER', 'BOOLEAN', 'SCALE', 'BLOOD_PRESSURE'], true)) {
            return null;
        }

        $fieldRecords = $records->filter(fn ($r) => $r->fieldName === $name);
        if ($fieldRecords->isEmpty()) {
            return null;
        }
        $inWindow = fn ($record, Carbon $from, Carbon $to) => Carbon::parse($record->recordDate)->startOfDay()->betweenIncluded($from, $to);

        if ($fieldType === 'BLOOD_PRESSURE') {
            $sysScore = self::numericScore(
                $fieldRecords->map(fn ($r) => ['date' => $r->recordDate, 'value' => is_array($r->value) ? ($r->value['tensio_sistolica'] ?? null) : null]),
                $last7From, $last7To, $prev7From, $prev7To, 'LOW', null, null // la pressió: com més baixa, millor (com al frontend).
            );
            $diaScore = self::numericScore(
                $fieldRecords->map(fn ($r) => ['date' => $r->recordDate, 'value' => is_array($r->value) ? ($r->value['tensio_diastolica'] ?? null) : null]),
                $last7From, $last7To, $prev7From, $prev7To, 'LOW', null, null
            );
            if ($sysScore === -1 || $diaScore === -1) {
                return -1;
            }
            if ($sysScore === 1 || $diaScore === 1) {
                return 1;
            }

            return null;
        }

        if ($direction === null || $direction === 'TARGET') {
            return null; // sense direcció bona, o "mantenir-se dins l'objectiu": no hi ha tendència a qualificar.
        }

        if ($fieldType === 'BOOLEAN') {
            $points = $fieldRecords->map(fn ($r) => ['date' => $r->recordDate, 'value' => in_array($r->value, [true, 1, '1', 'true'], true) ? 1.0 : 0.0]);

            return self::numericScore($points, $last7From, $last7To, $prev7From, $prev7To, $direction, $field->trendChangeAbs ?? null, $field->trendChangePct ?? null);
        }

        // NUMBER i SCALE: valor numèric directe.
        $points = $fieldRecords->map(fn ($r) => ['date' => $r->recordDate, 'value' => is_numeric($r->value) ? (float) $r->value : null]);
        $threshold = $fieldType === 'NUMBER' ? [$field->trendChangeAbs ?? null, $field->trendChangePct ?? null] : [null, null];

        return self::numericScore($points, $last7From, $last7To, $prev7From, $prev7To, $direction, $threshold[0], $threshold[1]);
    }

    /**
     * @param  Collection<int,array{date:string,value:?float}>  $points
     */
    private static function numericScore(Collection $points, Carbon $last7From, Carbon $last7To, Carbon $prev7From, Carbon $prev7To, string $direction, ?float $minAbs, ?float $minPct): ?int
    {
        $avgIn = function (Carbon $from, Carbon $to) use ($points) {
            $values = $points->filter(fn ($p) => $p['value'] !== null && Carbon::parse($p['date'])->startOfDay()->betweenIncluded($from, $to))->pluck('value');

            return $values->isEmpty() ? null : $values->avg();
        };
        $last7Avg = $avgIn($last7From, $last7To);
        $prev7Avg = $avgIn($prev7From, $prev7To);
        if ($last7Avg === null || $prev7Avg === null) {
            return null;
        }

        $deltaAbs = round(abs($prev7Avg - $last7Avg), 1);
        if ($deltaAbs === 0.0) {
            return null;
        }
        if ($minAbs !== null && $deltaAbs < $minAbs) {
            return null;
        }
        if ($minPct !== null && $prev7Avg != 0 && ($deltaAbs / abs($prev7Avg)) * 100 < $minPct) {
            return null;
        }

        $decreased = $last7Avg < $prev7Avg;
        $favorable = $direction === 'LOW' ? $decreased : ! $decreased;

        return $favorable ? 1 : -1;
    }
}
