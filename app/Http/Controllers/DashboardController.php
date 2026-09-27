<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\RoutineAssignment;
use App\Support\AccessLogger;
use App\Support\AlertGenerator;
use App\Support\ClinicalProgress;
use App\Support\FieldFrequencies;
use App\Support\FieldRules;
use App\Support\RoutineProgress;
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

    // Nutricionista overview
    public function overview(Request $request)
    {
        $nutricionistaId = $request->user()->id;
        $cutoff = now()->subDays(15);

        // Només pacients amb el compte actiu: un pacient eliminat (anonimitzat) manté la fila
        // sys_patients però ja no ha de comptar als indicadors.
        $ownPatients = fn () => Patient::where('nutricionistaId', $nutricionistaId)
            ->whereHas('user', fn ($q) => $q->whereNull('deletedAt'));
        $ownAssignments = fn () => RoutineAssignment::whereHas('patient', function ($q) use ($nutricionistaId) {
            $q->where('nutricionistaId', $nutricionistaId)->whereHas('user', fn ($u) => $u->whereNull('deletedAt'));
        });

        $patients = $ownPatients()->count();
        $newPatients = $ownPatients()->where('createdAt', '>=', $cutoff)->count();
        $activeAssignments = $ownAssignments()->where('status', 'ACTIVE')->count();
        // Rutines actives que es van assignar en els últims 15 dies.
        $newActiveAssignments = $ownAssignments()->where('status', 'ACTIVE')->where('createdAt', '>=', $cutoff)->count();
        $completedAssignments = $ownAssignments()->where('status', 'COMPLETED')->count();
        $newCompletedAssignments = $ownAssignments()->where('status', 'COMPLETED')->where('completedAt', '>=', $cutoff)->count();

        // Pacients amb almenys una rutina activa ("en seguiment").
        $patientsInFollowUp = $ownPatients()
            ->whereHas('assignments', fn ($q) => $q->where('status', 'ACTIVE'))
            ->count();
        $patientsInFollowUpPercent = $patients > 0 ? (int) round(($patientsInFollowUp / $patients) * 100) : 0;

        // % de rutines completades i valorades pel nutricionista que han anat bé, sobre
        // el total de rutines completades que sí que es van poder valorar (prou dades).
        $ratedAssignments = $ownAssignments()->whereNotNull('evolutionRating');
        $ratedTotal = (clone $ratedAssignments)->count();
        $positiveTotal = (clone $ratedAssignments)->where('evolutionRating', 'POSITIVE')->count();
        $positiveEvolutionPercent = $ratedTotal > 0 ? (int) round(($positiveTotal / $ratedTotal) * 100) : null;

        return response()->json([
            'totalPatients' => $patients,
            'newPatientsLast15Days' => $newPatients,
            'activeRoutines' => $activeAssignments,
            'newActiveRoutinesLast15Days' => $newActiveAssignments,
            'completedRoutines' => $completedAssignments,
            'newCompletedRoutinesLast15Days' => $newCompletedAssignments,
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

        // Adherència: els registres de camps setmanals no compten com a dia registrat; si tots els
        // camps de la rutina són setmanals es compten setmanes (veure RoutineProgress).
        $adherence = FieldFrequencies::adherenceFor($assignment->templateId);
        $countedRecordDates = $records->filter(fn ($r) => in_array($r->fieldName, $adherence['counted'], true))->pluck('recordDate');
        $progress = RoutineProgress::of($assignment->startDate, $assignment->endDate, $countedRecordDates, $adherence['mode']);
        // Progrés clínic: ritme de registre + evolució dels valors dels camps clau. Vegeu App\Support\ClinicalProgress.
        $keyFields = $assignment->template->fields->where('isKeyField', true);
        $trend = ClinicalProgress::of($assignment->startDate, $assignment->endDate, $countedRecordDates, $records, $keyFields);
        $totalDays = $progress['elapsedDays'];
        $fullDurationDays = $progress['totalDays'];
        $daysWithRecords = $progress['daysWithRecords'];
        $adherencePercent = $progress['adherencePercent'];
        $completedPercent = $progress['completedPercent'];

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
        $fieldByName = $assignment->template->fields->keyBy('name');
        $labelOf = fn (string $name) => $fieldLabelByName[$name] ?? $name;

        // Incidències (valors fora de rang): vegeu App\Support\AlertGenerator i docs/com-funcionen-les-alertes.md.
        $incidencies = AlertGenerator::incidents($assignment, $records);

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
        $urgents = $incidencies->where('level', 'URGENT');
        if ($urgents->isNotEmpty()) {
            $puntsARevisar[] = "{$urgents->count()} alerta".($urgents->count() === 1 ? '' : 's').' urgent'.($urgents->count() === 1 ? '' : 's').' ('.$urgents->pluck('field')->unique()->join(', ').'). Revisar-les primer.';
        }
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
            'trend' => $trend,
            'daysWithRecords' => $daysWithRecords,
            'totalDays' => $totalDays,
            'fullDurationDays' => $fullDurationDays,
            'progressUnit' => $progress['progressUnit'],
            'totalRecords' => $records->count(),
            'recordsByField' => $byField,
            'incidencies' => $incidencies->slice(-10)->reverse()->values(),
            'puntsARevisar' => $puntsARevisar,
            'days' => $days,
        ]);
    }

}
