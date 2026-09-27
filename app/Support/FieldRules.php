<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

// Regles d'alerta, tendència i adherència d'un camp (docs/disseny-migracions-regles-camps.md, Fase 1).
// Viuen a les columnes del camp (biblioteca -> plantilla, editables). Una columna buida vol dir "usa la regla
// del seu tipus": aquí hi ha les regles per defecte, que són codi (no dades).
//
// Fase 1: s'avaluen les alertes de Sí/No (alertValue) i de números (alertMin/alertMax), amb les regles de
// repetició. La resta de columnes (tendència, camp clau, adherència, sensible, fórmula) ja es desen i es
// copien, però encara no es fan servir enlloc del backend (vegeu l'estat al document de disseny).
class FieldRules
{
    public const COLUMNS = [
        'alertMin', 'alertMax', 'alertValue', 'alertLevel', 'alertMinOccurrences', 'alertWindowDays', 'alertConsecutiveDays',
        'trendChangeAbs', 'trendChangePct', 'trendWindowDays', 'isKeyField', 'countsForAdherence', 'isSensitive', 'calculation',
    ];

    public const CASTS = [
        'alertMin' => 'float', 'alertMax' => 'float', 'alertValue' => 'boolean',
        'alertMinOccurrences' => 'integer', 'alertWindowDays' => 'integer', 'alertConsecutiveDays' => 'integer',
        'trendChangeAbs' => 'float', 'trendChangePct' => 'float', 'trendWindowDays' => 'integer',
        'isKeyField' => 'boolean', 'countsForAdherence' => 'boolean', 'isSensitive' => 'boolean', 'calculation' => 'array',
    ];

    public const LEVELS = ['NONE', 'REVIEW', 'URGENT'];

    /** Només les columnes de regla d'un camp (model o array), per copiar-les entre biblioteca, plantilla i favorits. */
    public static function columnsFrom($source): array
    {
        $out = [];
        foreach (self::COLUMNS as $column) {
            if (is_array($source)) {
                if (array_key_exists($column, $source)) {
                    $out[$column] = $source[$column];
                }
            } else {
                $out[$column] = $source->{$column};
            }
        }

        return $out;
    }

    /** Regles de validació de les columnes d'un camp en una petició (`$prefix` = 'fields.*.' o ''). */
    public static function requestRules(string $prefix = ''): array
    {
        return [
            $prefix.'goodDirection' => ['nullable', Rule::in(['LOW', 'HIGH', 'TARGET'])],
            $prefix.'alertMin' => ['nullable', 'numeric', 'between:-999999,999999'],
            $prefix.'alertMax' => ['nullable', 'numeric', 'between:-999999,999999'],
            $prefix.'alertValue' => ['nullable', 'boolean'],
            $prefix.'alertLevel' => ['sometimes', Rule::in(self::LEVELS)],
            // Van juntes: "vegades" dins de "dies" (cal les dues o cap).
            $prefix.'alertMinOccurrences' => ['nullable', 'integer', 'min:1', 'max:60', 'required_with:'.$prefix.'alertWindowDays'],
            $prefix.'alertWindowDays' => ['nullable', 'integer', 'min:1', 'max:60', 'required_with:'.$prefix.'alertMinOccurrences'],
            $prefix.'alertConsecutiveDays' => ['nullable', 'integer', 'min:1', 'max:60'],
            $prefix.'trendChangeAbs' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            $prefix.'trendChangePct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            $prefix.'trendWindowDays' => ['sometimes', 'integer', 'min:1', 'max:60'],
            $prefix.'isKeyField' => ['sometimes', 'boolean'],
            $prefix.'countsForAdherence' => ['sometimes', 'boolean'],
            $prefix.'isSensitive' => ['sometimes', 'boolean'],
            $prefix.'calculation' => ['nullable', 'array'],
            $prefix.'sourceLibraryFieldId' => ['nullable', 'string', 'max:36'],
        ];
    }

    /** Missatges (en català) de les regles de repetició, per als requests que fan servir requestRules(). */
    public static function requestMessages(string $prefix = ''): array
    {
        $pair = 'Cal omplir les dues caselles de la repetició: vegades i dies.';

        return [$prefix.'alertMinOccurrences.required_with' => $pair, $prefix.'alertWindowDays.required_with' => $pair];
    }

    /**
     * Avalua un valor registrat contra les regles del camp. Retorna null si és dins del rang o si el camp
     * no té regla; si no, ['severity' => 'HIGH'|'LOW', 'reference' => text, 'level' => REVIEW|URGENT].
     * Escales i pressió arterial (amb la seva pròpia lògica) es continuen avaluant al DashboardController.
     */
    public static function evaluate($field, mixed $value): ?array
    {
        $level = $field->alertLevel ?? 'NONE';
        if ($level === 'NONE' || ($field->isSensitive ?? false)) {
            return null; // sense nivell d'alerta o camp sensible: no hi ha alerta automàtica
        }

        if ($field->fieldType === 'BOOLEAN' && $field->alertValue !== null) {
            $isYes = $value === true || $value === 1 || $value === '1' || $value === 'true';
            $isNo = $value === false || $value === 0 || $value === '0' || $value === 'false';
            if (! $isYes && ! $isNo) {
                return null;
            }
            if ($isYes === (bool) $field->alertValue) {
                return ['severity' => 'HIGH', 'reference' => $field->alertValue ? 'Sí és alerta' : 'No és alerta', 'level' => $level];
            }

            return null;
        }

        if ($field->fieldType === 'NUMBER' && is_numeric($value)) {
            $number = (float) $value;
            $min = $field->alertMin;
            $max = $field->alertMax;
            if ($max !== null && $number > (float) $max) {
                return ['severity' => 'HIGH', 'reference' => self::rangeText($min, $max, $field->unit), 'level' => $level];
            }
            if ($min !== null && $number < (float) $min) {
                return ['severity' => 'LOW', 'reference' => self::rangeText($min, $max, $field->unit), 'level' => $level];
            }
        }

        return null;
    }

    private static function rangeText($min, $max, $unit): string
    {
        $unit = $unit ? ' '.$unit : '';
        if ($min !== null && $max !== null) {
            return self::num($min).' – '.self::num($max).$unit;
        }

        return $max !== null ? '≤ '.self::num($max).$unit : '≥ '.self::num($min).$unit;
    }

    private static function num($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',');
    }

    /**
     * Aplica les regles de repetició d'un camp a les seves incidències:
     * `alertConsecutiveDays` = dies SEGUITS fora de rang; `alertMinOccurrences` dins d'`alertWindowDays`
     * (finestra per defecte de 7 dies). Sense cap de les dues, cada incidència compta.
     *
     * @param  Collection<int, array>  $incidents  incidències d'UN camp
     */
    public static function filterRepetitions(Collection $incidents, $field): Collection
    {
        $consecutive = (int) ($field->alertConsecutiveDays ?? 0);
        $occurrences = (int) ($field->alertMinOccurrences ?? 0);
        if ($incidents->isEmpty() || ($consecutive <= 1 && $occurrences <= 1)) {
            return $incidents;
        }

        $dates = $incidents->pluck('date')->unique()->sort()->values()->all();
        $day = fn (string $d): int => (int) (strtotime($d.' 00:00:00 UTC') / 86400);
        $keep = [];

        if ($consecutive > 1) {
            $run = [];
            foreach ($dates as $date) {
                if ($run && $day($date) - $day(end($run)) === 1) {
                    $run[] = $date;
                    continue;
                }
                if (count($run) >= $consecutive) {
                    $keep = array_merge($keep, $run);
                }
                $run = [$date];
            }
            if (count($run) >= $consecutive) {
                $keep = array_merge($keep, $run);
            }
        }

        if ($occurrences > 1) {
            $window = max(1, (int) ($field->alertWindowDays ?: 7)); // per seguretat, si les dades antigues no en tenen
            foreach ($dates as $date) {
                $inWindow = array_values(array_filter($dates, fn ($other) => $day($other) <= $day($date) && $day($date) - $day($other) < $window));
                if (count($inWindow) >= $occurrences) {
                    $keep = array_merge($keep, $inWindow); // compten tots els dies de la finestra que acaba en aquest
                }
            }
        }

        $keep = array_flip(array_unique($keep));

        return $incidents->filter(fn ($incident) => isset($keep[$incident['date']]))->values();
    }
}
