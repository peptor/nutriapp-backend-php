<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\RoutineAssignment;
use App\Support\AccessLogger;
use App\Support\UrlHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    private function professionalOf($nutricionista): array
    {
        $profile = $nutricionista->nutricionistaProfile;

        return [
            'name' => $nutricionista->name,
            'email' => $nutricionista->email,
            'companyName' => $profile?->companyName,
            'taxId' => $profile?->taxId,
            'collegiateNumber' => $profile?->collegiateNumber,
            'address' => $profile?->address,
            'postalCode' => $profile?->postalCode,
            'city' => $profile?->city,
            'phone' => $profile?->phone,
            'logoUrl' => UrlHelper::toAbsoluteUrl($profile?->logoUrl),
        ];
    }

    // Classificació de la pressió arterial segons l'edat del pacient (guies clíniques
    // generals; per defecte -edat desconeguda- es fa servir la franja d'adult 18-64).
    // Retorna null si els valors estan dins del rang de referència.
    private function bloodPressureIncidence(float $sys, float $dia, ?int $age): ?array
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

        // Adults (18-64 anys) i per defecte quan no es coneix l'edat del pacient.
        return ($sys >= 130 || $dia >= 85) ? ['severity' => 'HIGH', 'reference' => '< 130 / 85 mmHg'] : null;
    }

    // Nutricionista overview
    public function overview(Request $request)
    {
        $nutricionistaId = $request->user()->id;
        $lastMonthCutoff = now()->subDays(30);

        $patients = Patient::where('nutricionistaId', $nutricionistaId)->count();
        $newPatients = Patient::where('nutricionistaId', $nutricionistaId)->where('createdAt', '>=', $lastMonthCutoff)->count();
        $activeAssignments = RoutineAssignment::where('status', 'ACTIVE')
            ->whereHas('patient', fn ($q) => $q->where('nutricionistaId', $nutricionistaId))->count();
        $newActiveAssignments = RoutineAssignment::where('status', 'ACTIVE')->where('createdAt', '>=', $lastMonthCutoff)
            ->whereHas('patient', fn ($q) => $q->where('nutricionistaId', $nutricionistaId))->count();

        // Pacients amb almenys una rutina activa ("en seguiment").
        $patientsInFollowUp = Patient::where('nutricionistaId', $nutricionistaId)
            ->whereHas('assignments', fn ($q) => $q->where('status', 'ACTIVE'))
            ->count();
        $patientsInFollowUpPercent = $patients > 0 ? (int) round(($patientsInFollowUp / $patients) * 100) : 0;

        // % de rutines completades i valorades pel nutricionista que han anat bé, sobre
        // el total de rutines completades que sí que es van poder valorar (prou dades).
        $ratedAssignments = RoutineAssignment::whereNotNull('evolutionRating')
            ->whereHas('patient', fn ($q) => $q->where('nutricionistaId', $nutricionistaId));
        $ratedTotal = (clone $ratedAssignments)->count();
        $positiveTotal = (clone $ratedAssignments)->where('evolutionRating', 'POSITIVE')->count();
        $positiveEvolutionPercent = $ratedTotal > 0 ? (int) round(($positiveTotal / $ratedTotal) * 100) : null;

        return response()->json([
            'totalPatients' => $patients,
            'newPatientsLastMonth' => $newPatients,
            'activeRoutines' => $activeAssignments,
            'newActiveRoutinesLastMonth' => $newActiveAssignments,
            'patientsInFollowUp' => $patientsInFollowUp,
            'patientsInFollowUpPercent' => $patientsInFollowUpPercent,
            'positiveEvolutionPercent' => $positiveEvolutionPercent,
            'ratedAssignmentsTotal' => $ratedTotal,
        ]);
    }

    // Resum complet d'una assignació (adherència + evolució)
    public function summary(Request $request, string $assignmentId)
    {
        $assignment = RoutineAssignment::with([
            'template.fields' => fn ($q) => $q->orderBy('orderIndex'),
            'template.fields.fieldIcon',
            'patient.user:id,name,email',
            'patient.nutricionista:id,name,email',
            'patient.nutricionista.nutricionistaProfile',
        ])->find($assignmentId);

        if (! $assignment) {
            return response()->json(['error' => 'Assignació no trobada'], 404);
        }
        // Cal carregar els registres ordenats a part (Eloquent no ordena "with" per múltiples camps sobre relació ja definida)
        $records = $assignment->records()->orderBy('recordDate')->orderBy('fieldName')->get();

        $user = $request->user();
        $isOwnerNutricionista = $user->role === 'NUTRICIONISTA' && $assignment->patient->nutricionistaId === $user->id;
        $isOwnerPacient = $user->role === 'PACIENT' && $assignment->patient->userId === $user->id;
        if (! $isOwnerNutricionista && ! $isOwnerPacient) {
            return response()->json(['error' => 'Accés denegat'], 403);
        }

        if ($isOwnerNutricionista) {
            AccessLogger::log($user->id, $user->role, 'VIEW_SUMMARY', 'RoutineAssignment', $assignment->id);
        }

        $start = Carbon::parse($assignment->startDate)->startOfDay();
        $end = Carbon::parse($assignment->endDate)->startOfDay();
        $today = Carbon::now('UTC')->startOfDay();
        $effectiveEnd = $today->lt($end) ? $today : $end;

        $totalDays = max(1, $start->diffInDays($effectiveEnd) + 1);
        $fullDurationDays = max(1, $start->diffInDays($end) + 1);

        $daysWithRecordsSet = $records->map(fn ($r) => Carbon::parse($r->recordDate)->toDateString())->unique();
        $daysWithRecords = $daysWithRecordsSet->count();
        $adherencePercent = (int) round(($daysWithRecords / $totalDays) * 100);
        $completedPercent = (int) round(($daysWithRecords / $fullDurationDays) * 100);

        $byField = [];
        foreach ($records as $r) {
            $byField[$r->fieldName][] = [
                'date' => Carbon::parse($r->recordDate)->toDateString(),
                'value' => $r->value,
                'notes' => $r->notes,
            ];
        }

        $fieldTypeByName = $assignment->template->fields->pluck('fieldType', 'name');
        $fieldLabelByName = $assignment->template->fields->pluck('label', 'name');
        $fieldGoodDirectionByName = $assignment->template->fields->pluck('goodDirection', 'name');
        $labelOf = fn (string $name) => $fieldLabelByName[$name] ?? $name;

        // Incidències: valors fora del rang recomanat. De moment només es detecten per a
        // dos tipus de camp:
        // - Escala 0-10: només si el camp té una "direcció bona" definida, perquè si no no
        //   sabem quin extrem (0-2 o 8-10) és el preocupant. L'extrem contrari a la direcció
        //   bona és el que es marca com a incidència.
        // - Pressió arterial: segons l'edat del pacient (veure bloodPressureIncidence()).
        $patientAge = $assignment->patient->birthDate ? Carbon::parse($assignment->patient->birthDate)->age : null;
        $incidencies = $records
            ->map(function ($r) use ($fieldTypeByName, $fieldGoodDirectionByName, $labelOf, $patientAge) {
                $fieldType = $fieldTypeByName[$r->fieldName] ?? null;
                $date = Carbon::parse($r->recordDate)->toDateString();
                $field = $labelOf($r->fieldName);

                if ($fieldType === 'SCALE' && is_numeric($r->value)) {
                    $value = (float) $r->value;
                    $direction = $fieldGoodDirectionByName[$r->fieldName] ?? null;
                    if ($direction === 'LOW' && $value >= 8) {
                        return ['date' => $date, 'field' => $field, 'value' => $r->value, 'severity' => 'HIGH', 'reference' => '0 – 7'];
                    }
                    if ($direction === 'HIGH' && $value <= 2) {
                        return ['date' => $date, 'field' => $field, 'value' => $r->value, 'severity' => 'LOW', 'reference' => '3 – 10'];
                    }

                    return null;
                }

                if ($fieldType === 'BLOOD_PRESSURE' && is_array($r->value)) {
                    $sys = is_numeric($r->value['tensio_sistolica'] ?? null) ? (float) $r->value['tensio_sistolica'] : null;
                    $dia = is_numeric($r->value['tensio_diastolica'] ?? null) ? (float) $r->value['tensio_diastolica'] : null;
                    if ($sys === null || $dia === null) {
                        return null;
                    }
                    $bp = $this->bloodPressureIncidence($sys, $dia, $patientAge);
                    if ($bp === null) {
                        return null;
                    }

                    return ['date' => $date, 'field' => $field, 'value' => "{$r->value['tensio_sistolica']} / {$r->value['tensio_diastolica']}", 'severity' => $bp['severity'], 'reference' => $bp['reference']];
                }

                return null;
            })
            ->filter()
            ->values();

        // Camps NUMBER (a diferència de SCALE/BOOLEAN/BLOOD_PRESSURE/TEXT/MEAL) que
        // tendeixen a pujar: primer i últim valor registrat en el període.
        $risingNumberFields = collect($byField)
            ->reject(fn ($entries, $field) => $field === 'food_log' || in_array($fieldTypeByName[$field] ?? null, ['MEAL', 'SCALE', 'BOOLEAN', 'BLOOD_PRESSURE', 'TEXT'], true))
            ->map(function ($entries, $field) use ($labelOf) {
                $nums = collect($entries)->map(fn ($e) => is_numeric($e['value']) ? (float) $e['value'] : null)->filter(fn ($n) => $n !== null)->values();
                if ($nums->count() < 2) {
                    return null;
                }

                return ['field' => $labelOf($field), 'first' => $nums->first(), 'last' => $nums->last()];
            })
            ->filter();

        $puntsARevisar = [];
        if ($adherencePercent < 60) {
            $puntsARevisar[] = "Adherència baixa ($adherencePercent%). Valorar obstacles o simplificar la rutina.";
        }
        if ($incidencies->count() >= 3) {
            $puntsARevisar[] = "{$incidencies->count()} registres fora del rang recomanat. Revisar símptomes i context.";
        }
        foreach ($risingNumberFields as $v) {
            if ($v['last'] > $v['first'] && $v['last'] >= 6) {
                $puntsARevisar[] = "La variable \"{$v['field']}\" tendeix a pujar (últim valor: {$v['last']}).";
            }
        }
        if (empty($puntsARevisar)) {
            $puntsARevisar[] = 'Sense alertes destacades. Revisar evolució general i objectius.';
        }

        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dateStr = $cursor->toDateString();
            $dayRecords = $records->filter(fn ($r) => Carbon::parse($r->recordDate)->toDateString() === $dateStr)->values();
            if ($dayRecords->isNotEmpty()) {
                $status = 'registered';
            } elseif ($cursor->gt($today)) {
                $status = 'future';
            } elseif ($cursor->lt($today)) {
                $status = 'missed';
            } else {
                $status = 'pending';
            }
            $days[] = ['date' => $dateStr, 'status' => $status, 'records' => $dayRecords];
            $cursor->addDay();
        }

        return response()->json([
            'assignment' => [
                'id' => $assignment->id,
                'patientId' => $assignment->patientId,
                'startDate' => $assignment->startDate,
                'endDate' => $assignment->endDate,
                'status' => $assignment->status,
                'templateName' => $assignment->template->name,
                'objective' => $assignment->template->objective,
                'patientName' => $assignment->patient->user->name,
                'patientBirthDate' => $assignment->patient->birthDate,
                'fields' => $assignment->template->fields,
                'foodLogEnabled' => $assignment->template->foodLogEnabled,
            ],
            'professional' => $this->professionalOf($assignment->patient->nutricionista),
            'adherencePercent' => $adherencePercent,
            'completedPercent' => $completedPercent,
            'daysWithRecords' => $daysWithRecords,
            'totalDays' => $totalDays,
            'fullDurationDays' => $fullDurationDays,
            'totalRecords' => $records->count(),
            'recordsByField' => $byField,
            'incidencies' => $incidencies->slice(-10)->reverse()->values(),
            'puntsARevisar' => $puntsARevisar,
            'days' => $days,
        ]);
    }

}
