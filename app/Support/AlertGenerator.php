<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\RoutineAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

// Incidències d'una assignació (valors fora de rang) i alertes persistides (reg_alerts) que en surten.
// Vegeu docs/com-funcionen-les-alertes.md.
//
// Què és una incidència:
// - Escala (rang configurable, per defecte 0-10): només si el camp té direcció bona (LOW/HIGH). L'extrem contrari és
//   incidència: el 20 % inferior o superior del rang. Nivell REVIEW.
// - Pressió arterial: segons l'edat del pacient (bloodPressureIncidence). Nivell REVIEW.
// - Sí/No i números amb regla d'alerta al camp (columnes alert*, FieldRules::evaluate): el nivell és el del camp.
// - Un camp sensible (isSensitive) no genera alertes.
// Després s'apliquen les regles de repetició del camp (dies seguits / vegades en una finestra).
class AlertGenerator
{
    /**
     * @param  Collection<int, \App\Models\DailyRecord>  $records  registres de l'assignació (ordenats per data)
     * @return Collection<int, array{date: string, fieldName: string, field: string, value: mixed, severity: string, reference: string, level: string}>
     */
    public static function incidents(RoutineAssignment $assignment, Collection $records): Collection
    {
        $fieldByName = $assignment->template->fields->keyBy('name');
        $patientAge = $assignment->patient->birthDate ? Carbon::parse($assignment->patient->birthDate)->age : null;

        $incidents = $records
            ->map(function ($r) use ($fieldByName, $patientAge) {
                $field = $fieldByName[$r->fieldName] ?? null;
                if (! $field || ($field->isSensitive ?? false)) {
                    return null;
                }
                $date = Carbon::parse($r->recordDate)->toDateString();
                $base = ['date' => $date, 'fieldName' => $r->fieldName, 'field' => $field->label];

                if ($field->fieldType === 'SCALE' && is_numeric($r->value)) {
                    $value = (float) $r->value;
                    $direction = $field->goodDirection;
                    $scaleMin = (int) ($field->scaleMin ?? 0);
                    $scaleMax = (int) ($field->scaleMax ?? 10);
                    $span = $scaleMax - $scaleMin;
                    $highFrom = $scaleMin + intdiv(8 * $span + 9, 10); // primer valor del 20 % superior
                    $lowTo = $scaleMin + intdiv(2 * $span, 10); // últim valor del 20 % inferior
                    if ($direction === 'LOW' && $value >= $highFrom) {
                        return $base + ['value' => $r->value, 'severity' => 'HIGH', 'reference' => $scaleMin.' – '.($highFrom - 1), 'level' => 'REVIEW'];
                    }
                    if ($direction === 'HIGH' && $value <= $lowTo) {
                        return $base + ['value' => $r->value, 'severity' => 'LOW', 'reference' => ($lowTo + 1).' – '.$scaleMax, 'level' => 'REVIEW'];
                    }

                    return null;
                }

                if (in_array($field->fieldType, ['BOOLEAN', 'NUMBER'], true)) {
                    $alert = FieldRules::evaluate($field, $r->value);
                    if ($alert === null) {
                        return null;
                    }
                    $shown = $field->fieldType === 'BOOLEAN' ? (in_array($r->value, [true, 1, '1', 'true'], true) ? 'Sí' : 'No') : $r->value;

                    return $base + ['value' => $shown, 'severity' => $alert['severity'], 'reference' => $alert['reference'], 'level' => $alert['level']];
                }

                if ($field->fieldType === 'BLOOD_PRESSURE' && is_array($r->value)) {
                    $sys = is_numeric($r->value['tensio_sistolica'] ?? null) ? (float) $r->value['tensio_sistolica'] : null;
                    $dia = is_numeric($r->value['tensio_diastolica'] ?? null) ? (float) $r->value['tensio_diastolica'] : null;
                    if ($sys === null || $dia === null) {
                        return null;
                    }
                    $bp = self::bloodPressureIncidence($sys, $dia, $patientAge);
                    if ($bp === null) {
                        return null;
                    }

                    return $base + ['value' => "{$r->value['tensio_sistolica']} / {$r->value['tensio_diastolica']}", 'severity' => $bp['severity'], 'reference' => $bp['reference'], 'level' => 'REVIEW'];
                }

                return null;
            })
            ->filter()
            ->values();

        // Regles de repetició del camp (dies seguits o vegades dins d'una finestra), si en té.
        return $incidents->groupBy('fieldName')
            ->flatMap(fn ($items, $name) => isset($fieldByName[$name]) ? FieldRules::filterRepetitions($items->values(), $fieldByName[$name]) : $items)
            ->sortBy('date')
            ->values();
    }

    // Classificació de la pressió arterial segons l'edat del pacient (guies clíniques generals; per defecte,
    // edat desconeguda, la franja d'adult 18-64). Retorna null si els valors són dins del rang de referència.
    public static function bloodPressureIncidence(float $sys, float $dia, ?int $age): ?array
    {
        if ($age !== null && $age <= 5) {
            return ($sys > 110 || $dia > 79) ? ['severity' => 'HIGH', 'reference' => '≤ 110 / 79 mmHg'] : null;
        }
        if ($age !== null && $age <= 13) {
            return ($sys > 115 || $dia > 80) ? ['severity' => 'HIGH', 'reference' => '≤ 115 / 80 mmHg'] : null;
        }
        if ($age !== null && $age >= 80) {
            if ($sys > 150) {
                return ['severity' => 'HIGH', 'reference' => '140 – 150 / > 70 mmHg'];
            }

            return $dia < 70 ? ['severity' => 'LOW', 'reference' => '140 – 150 / > 70 mmHg'] : null;
        }
        if ($age !== null && $age >= 65) {
            return ($sys >= 140 || $dia >= 90) ? ['severity' => 'HIGH', 'reference' => '< 140 / 90 mmHg'] : null;
        }

        return ($sys >= 130 || $dia >= 85) ? ['severity' => 'HIGH', 'reference' => '< 130 / 85 mmHg'] : null;
    }

    // Camp sentinella de les alertes MISSING_DAYS (reg_alerts.fieldName no és nul·lable i l'alerta no és d'un
    // camp concret, és de tota l'assignació): mai coincideix amb el `name` real d'un camp.
    public const MISSING_DAYS_FIELD = '__assignment__';

    /** Dies seguits sense registrar perquè passi de recordatori (push) a alerta real al tauler. Veure SyncMissingDaysAlerts. */
    public const MISSING_DAYS_THRESHOLD = 3;

    /**
     * Posa al dia les alertes (reg_alerts) d'una assignació: crea les incidències noves (OUT_OF_RANGE/ALARM),
     * actualitza les existents (sense tocar-ne l'estat) i elimina les obertes o vistes que ja no ho són (un
     * registre editat). Les resoltes es conserven. També sincronitza TREND_WORSE (mateix càlcul que "Progrés
     * clínic", camp a camp) i resol les MISSING_DAYS obertes (si hi ha un registre nou, el pacient ja ha tornat
     * a registrar). Les alertes URGENT noves envien un avís push (genèric, sense dades de salut) al
     * nutricionista responsable i al mateix pacient (decisió "nivell 4" de docs/disseny-migracions-regles-camps.md),
     * cadascun només si té les push autoritzades. Per ara totes les alertes es consideren de les dues audiències:
     * encara falta decidir quines són només del nutricionista (docs/com-funcionen-les-alertes.md, secció 10).
     */
    public static function sync(RoutineAssignment $assignment): void
    {
        $assignment->loadMissing(['template.fields', 'patient.nutricionista', 'patient.user']);
        $records = $assignment->records()->orderBy('recordDate')->orderBy('fieldName')->get();
        $recordIds = $records->mapWithKeys(fn ($r) => [$r->fieldName.'|'.Carbon::parse($r->recordDate)->toDateString() => $r->id]);

        $keep = [];
        $newUrgent = 0;
        foreach (self::incidents($assignment, $records) as $incident) {
            $type = self::typeOf($assignment, $incident['fieldName']);
            $key = $incident['fieldName'].'|'.$incident['date'].'|'.$type;
            $keep[$key] = true;

            $alert = Alert::updateOrCreate(
                ['assignmentId' => $assignment->id, 'fieldName' => $incident['fieldName'], 'recordDate' => $incident['date'], 'type' => $type],
                [
                    'recordId' => $recordIds[$incident['fieldName'].'|'.$incident['date']] ?? null,
                    'level' => $incident['level'],
                    'severity' => $incident['severity'],
                    'value' => mb_substr((string) $incident['value'], 0, 120),
                    'reference' => mb_substr((string) $incident['reference'], 0, 120),
                    'message' => mb_substr($incident['field'].': '.$incident['value'].($type === 'ALARM' ? '' : ' (referència '.$incident['reference'].')'), 0, 300),
                ]
            );
            if ($alert->wasRecentlyCreated && $alert->level === 'URGENT') {
                $newUrgent++;
            }
        }

        // Només els tipus que surten de incidents() (OUT_OF_RANGE/ALARM, lligats a un registre concret d'un
        // dia): TREND_WORSE i MISSING_DAYS tenen el seu propi cicle de vida més avall (syncTrendWorse,
        // resolveMissingDays) — si entressin aquí, com que mai són a $keep, es tornarien a esborrar tot seguit.
        Alert::where('assignmentId', $assignment->id)->whereIn('type', ['OUT_OF_RANGE', 'ALARM'])->whereIn('status', ['OPEN', 'SEEN'])->get()
            ->each(function (Alert $alert) use ($keep) {
                if (! isset($keep[$alert->fieldName.'|'.$alert->recordDate->toDateString().'|'.$alert->type])) {
                    $alert->delete();
                }
            });

        self::syncTrendWorse($assignment, $records);
        self::resolveMissingDays($assignment);

        if ($newUrgent > 0) {
            $pushSender = app(PushSender::class);
            if ($nutricionista = $assignment->patient->nutricionista) {
                $pushSender->sendToUser(
                    $nutricionista,
                    'NutriEvo',
                    Translator::t($newUrgent === 1 ? 'urgent_alert_nutri_one' : 'urgent_alert_nutri_many', $nutricionista->language, ['n' => $newUrgent]),
                    '/alerts',
                    'urgent-alert'
                );
            }
            if ($patientUser = $assignment->patient->user) {
                $pushSender->sendToUser(
                    $patientUser,
                    'NutriEvo',
                    Translator::t($newUrgent === 1 ? 'urgent_alert_patient_one' : 'urgent_alert_patient_many', $patientUser->language, ['n' => $newUrgent]),
                    '/patient/notices',
                    'urgent-alert'
                );
            }
        }
    }

    private static function typeOf(RoutineAssignment $assignment, string $fieldName): string
    {
        return ($assignment->template->fields->firstWhere('name', $fieldName)?->fieldType) === 'BOOLEAN' ? 'ALARM' : 'OUT_OF_RANGE';
    }

    // TREND_WORSE: mateix càlcul que App\Support\ClinicalProgress (mitjana dels últims 7 dies contra els 7
    // anteriors, amb els llindars trendChangeAbs/trendChangePct si n'hi ha), però per a CADA camp avaluable de
    // la rutina (no només els clau) — decisió de l'usuari (30/09/2026). Es repeteix com a molt un cop per
    // setmana per camp (si ja n'hi ha una d'oberta, o se'n va crear una en els últims 7 dies, no se'n crea una
    // altra); si el camp deixa de ser desfavorable, es tanca la que hi hagués oberta. Nivell REVIEW, sense push
    // (decisió de l'usuari).
    private static function syncTrendWorse(RoutineAssignment $assignment, Collection $records): void
    {
        $today = Carbon::today();
        $last7From = $today->copy()->subDays(6);
        $prev7To = $last7From->copy()->subDay();
        $prev7From = $prev7To->copy()->subDays(6);
        $recentSince = $today->copy()->subDays(6);

        foreach ($assignment->template->fields as $field) {
            if ($field->isSensitive) {
                continue;
            }

            $worsening = ClinicalProgress::fieldScore($field, $records, $last7From, $today, $prev7From, $prev7To) === -1;

            $open = Alert::where('assignmentId', $assignment->id)
                ->where('fieldName', $field->name)
                ->where('type', 'TREND_WORSE')
                ->whereIn('status', ['OPEN', 'SEEN'])
                ->first();

            if (! $worsening) {
                $open?->delete(); // Ja no és desfavorable: es tanca si n'hi havia una d'oberta.
                continue;
            }
            if ($open) {
                continue; // Ja n'hi ha una d'oberta per aquest camp.
            }
            $createdRecently = Alert::where('assignmentId', $assignment->id)
                ->where('fieldName', $field->name)
                ->where('type', 'TREND_WORSE')
                ->where('createdAt', '>=', $recentSince)
                ->exists();
            if ($createdRecently) {
                continue; // Ja se n'ha creat una (encara que ara estigui vista/resolta) en els últims 7 dies.
            }

            Alert::create([
                'assignmentId' => $assignment->id,
                'fieldName' => $field->name,
                'recordDate' => $today->toDateString(),
                'type' => 'TREND_WORSE',
                'level' => 'REVIEW',
                'severity' => 'HIGH',
                'message' => mb_substr($field->label.': la tendència d\'aquesta setmana és desfavorable respecte a la setmana anterior.', 0, 300),
            ]);
        }
    }

    // MISSING_DAYS: la crea SyncMissingDaysAlerts (comanda programada diària — cal un cron, no es pot detectar
    // en desar un registre perquè el que passa és que no n'hi ha cap). Aquí només es resol: si s'ha arribat a
    // sync() és perquè l'assignació acaba de rebre un registre, així que la ratxa sense registrar ja s'ha trencat.
    private static function resolveMissingDays(RoutineAssignment $assignment): void
    {
        Alert::where('assignmentId', $assignment->id)
            ->where('type', 'MISSING_DAYS')
            ->whereIn('status', ['OPEN', 'SEEN'])
            ->delete();
    }
}
