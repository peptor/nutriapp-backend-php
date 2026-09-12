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
            'companyName' => $profile?->companyName,
            'taxId' => $profile?->taxId,
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
        $lastMonthCutoff = now()->subDays(30);

        $patients = Patient::where('nutricionistaId', $nutricionistaId)->count();
        $newPatients = Patient::where('nutricionistaId', $nutricionistaId)->where('createdAt', '>=', $lastMonthCutoff)->count();
        $activeAssignments = RoutineAssignment::where('status', 'ACTIVE')
            ->whereHas('patient', fn ($q) => $q->where('nutricionistaId', $nutricionistaId))->count();
        $newActiveAssignments = RoutineAssignment::where('status', 'ACTIVE')->where('createdAt', '>=', $lastMonthCutoff)
            ->whereHas('patient', fn ($q) => $q->where('nutricionistaId', $nutricionistaId))->count();

        return response()->json([
            'totalPatients' => $patients,
            'newPatientsLastMonth' => $newPatients,
            'activeRoutines' => $activeAssignments,
            'newActiveRoutinesLastMonth' => $newActiveAssignments,
        ]);
    }

    // Resum complet d'una assignació (adherència + evolució)
    public function summary(Request $request, string $assignmentId)
    {
        $assignment = RoutineAssignment::with([
            'template.fields' => fn ($q) => $q->orderBy('orderIndex'),
            'patient.user:id,name,email',
            'patient.nutricionista:id,name',
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
            'days' => $days,
        ]);
    }

    // Informe preconsulta (més llegible per a la visita)
    public function preconsulta(Request $request, string $assignmentId)
    {
        $assignment = RoutineAssignment::with([
            'template.fields' => fn ($q) => $q->orderBy('orderIndex'),
            'patient.user:id,name,email,phone',
            'patient.nutricionista:id,name',
            'patient.nutricionista.nutricionistaProfile',
        ])->find($assignmentId);

        if (! $assignment) {
            return response()->json(['error' => 'Assignació no trobada'], 404);
        }
        if ($assignment->patient->nutricionistaId !== $request->user()->id) {
            return response()->json(['error' => 'Accés denegat'], 403);
        }

        $records = $assignment->records()->orderBy('recordDate')->orderBy('fieldName')->get();

        AccessLogger::log($request->user()->id, $request->user()->role, 'VIEW_PRECONSULTA', 'RoutineAssignment', $assignment->id);

        $start = Carbon::parse($assignment->startDate)->startOfDay();
        $end = Carbon::parse($assignment->endDate)->startOfDay();
        $today = Carbon::now('UTC')->startOfDay();
        $effectiveEnd = $today->lt($end) ? $today : $end;
        $totalDays = max(1, $start->diffInDays($effectiveEnd) + 1);

        $daysWithRecords = $records->map(fn ($r) => Carbon::parse($r->recordDate)->toDateString())->unique()->count();
        $adherencePercent = (int) round(($daysWithRecords / $totalDays) * 100);

        $byField = [];
        foreach ($records as $r) {
            $byField[$r->fieldName][] = ['date' => Carbon::parse($r->recordDate)->toDateString(), 'value' => $r->value, 'notes' => $r->notes];
        }

        $fieldTypeByName = $assignment->template->fields->pluck('fieldType', 'name');

        $variableSummaries = collect($byField)->map(function ($entries, $field) use ($fieldTypeByName) {
            if (($fieldTypeByName[$field] ?? null) === 'BLOOD_PRESSURE') {
                $systolics = collect($entries)->map(fn ($e) => is_numeric($e['value']['tensio_sistolica'] ?? null) ? (float) $e['value']['tensio_sistolica'] : null)->filter(fn ($n) => $n !== null);
                $diastolics = collect($entries)->map(fn ($e) => is_numeric($e['value']['tensio_diastolica'] ?? null) ? (float) $e['value']['tensio_diastolica'] : null)->filter(fn ($n) => $n !== null);
                $avg = fn ($nums) => round($nums->avg(), 1);

                return [
                    'field' => $field, 'type' => 'blood_pressure', 'count' => count($entries),
                    'avgSystolic' => $systolics->isNotEmpty() ? $avg($systolics) : null,
                    'avgDiastolic' => $diastolics->isNotEmpty() ? $avg($diastolics) : null,
                ];
            }

            $nums = collect($entries)->map(fn ($e) => is_numeric($e['value']) ? (float) $e['value'] : null)->filter(fn ($n) => $n !== null)->values();
            if ($nums->isEmpty()) {
                return ['field' => $field, 'type' => 'text', 'count' => count($entries), 'lastValue' => end($entries)['value'] ?? null];
            }
            $first = $nums->first();
            $last = $nums->last();

            return [
                'field' => $field, 'type' => 'number', 'count' => $nums->count(),
                'avg' => round($nums->avg(), 1), 'min' => $nums->min(), 'max' => $nums->max(),
                'first' => $first, 'last' => $last,
                'trend' => $last > $first ? 'puja' : ($last < $first ? 'baixa' : 'estable'),
            ];
        })->values();

        // Incidències senzilles: valors alts (>= 7 en escales 0-10). Només té sentit
        // per a camps d'escala: un NUMBER (p. ex. pes) no és una escala 0-10.
        $incidencies = $records->filter(fn ($r) => ($fieldTypeByName[$r->fieldName] ?? null) === 'SCALE' && is_numeric($r->value))
            ->filter(fn ($r) => (float) $r->value >= 7)
            ->map(fn ($r) => ['date' => Carbon::parse($r->recordDate)->toDateString(), 'field' => $r->fieldName, 'value' => $r->value])
            ->values();

        $puntsARevisar = [];
        if ($adherencePercent < 60) {
            $puntsARevisar[] = "Adherència baixa ($adherencePercent%). Valorar obstacles o simplificar la rutina.";
        }
        if ($incidencies->count() >= 3) {
            $puntsARevisar[] = "{$incidencies->count()} registres amb valors alts (≥7). Revisar símptomes i context.";
        }
        foreach ($variableSummaries as $v) {
            if (($v['type'] ?? null) === 'number' && ($v['trend'] ?? null) === 'puja' && $v['last'] >= 6) {
                $puntsARevisar[] = "La variable \"{$v['field']}\" tendeix a pujar (últim valor: {$v['last']}).";
            }
        }
        if (empty($puntsARevisar)) {
            $puntsARevisar[] = 'Sense alertes destacades. Revisar evolució general i objectius.';
        }

        return response()->json([
            'pacient' => [
                'id' => $assignment->patient->id,
                'name' => $assignment->patient->user->name,
                'email' => $assignment->patient->user->email,
                'phone' => $assignment->patient->user->phone,
                'gender' => $assignment->patient->gender,
                'photoUrl' => UrlHelper::toAbsoluteUrl($assignment->patient->photoUrl),
            ],
            'professional' => $this->professionalOf($assignment->patient->nutricionista),
            'rutina' => [
                'assignmentId' => $assignment->id,
                'templateName' => $assignment->template->name,
                'objective' => $assignment->template->objective,
                'status' => $assignment->status,
                'startDate' => $assignment->startDate,
                'endDate' => $assignment->endDate,
            ],
            'adherencia' => [
                'percent' => $adherencePercent,
                'daysWithRecords' => $daysWithRecords,
                'totalDays' => $totalDays,
                'totalRecords' => $records->count(),
            ],
            'variables' => $variableSummaries,
            'incidencies' => $incidencies->slice(-10)->values(),
            'puntsARevisar' => $puntsARevisar,
            'generatedAt' => now()->toIso8601String(),
        ]);
    }
}
