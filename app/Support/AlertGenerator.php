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

    /**
     * Posa al dia les alertes (reg_alerts) d'una assignació: crea les incidències noves, actualitza les existents
     * (sense tocar-ne l'estat) i elimina les obertes o vistes que ja no ho són (un registre editat). Les resoltes es conserven.
     * Les alertes URGENT noves envien un avís push (genèric, sense dades de salut) al nutricionista responsable
     * i al mateix pacient (decisió "nivell 4" de docs/disseny-migracions-regles-camps.md), cadascun només si té
     * les push autoritzades. Per ara totes les alertes es consideren de les dues audiències: encara falta decidir
     * quines són només del nutricionista (docs/com-funcionen-les-alertes.md, secció 10).
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

        Alert::where('assignmentId', $assignment->id)->whereIn('status', ['OPEN', 'SEEN'])->get()
            ->each(function (Alert $alert) use ($keep) {
                if (! isset($keep[$alert->fieldName.'|'.$alert->recordDate->toDateString().'|'.$alert->type])) {
                    $alert->delete();
                }
            });

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
}
