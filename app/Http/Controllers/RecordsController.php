<?php

namespace App\Http\Controllers;

use App\Http\Requests\BatchRecordsRequest;
use App\Http\Requests\DailyRecordRequest;
use App\Models\DailyRecord;
use App\Models\RoutineAssignment;
use App\Support\AccessLogger;
use App\Support\FieldValueValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RecordsController extends Controller
{
    private function isWithinAssignment(Carbon $date, RoutineAssignment $assignment): bool
    {
        $start = Carbon::parse($assignment->startDate)->startOfDay();
        $end = Carbon::parse($assignment->endDate)->startOfDay();

        return $date->gte($start) && $date->lte($end);
    }

    private function isMissingValue(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && array_is_list($value) && count($value) === 0);
    }

    // Resol l'assignació i comprova que qui fa la petició hi té accés (el pacient propietari
    // o el seu nutricionista). Retorna [assignment, null] o [null, JsonResponse d'error].
    private function resolveAssignmentForWrite(Request $request, string $assignmentId): array
    {
        $assignment = RoutineAssignment::where('id', $assignmentId)->where('status', 'ACTIVE')
            ->with(['template.fields', 'patient'])->first();
        if (! $assignment) {
            return [null, response()->json(['error' => 'Assignació no trobada o inactiva'], 404)];
        }
        $user = $request->user();
        if ($user->role === 'PACIENT' && $assignment->patient->userId !== $user->id) {
            return [null, response()->json(['error' => 'Accés denegat'], 403)];
        }
        if ($user->role === 'NUTRICIONISTA' && $assignment->patient->nutricionistaId !== $user->id) {
            return [null, response()->json(['error' => 'Accés denegat'], 403)];
        }

        return [$assignment, null];
    }

    // Create or update daily record (pacient, o el seu nutricionista en nom seu)
    public function store(DailyRecordRequest $request)
    {
        $data = $request->validated();
        [$assignment, $error] = $this->resolveAssignmentForWrite($request, $data['assignmentId']);
        if ($error) {
            return $error;
        }

        $date = Carbon::parse($data['recordDate'])->startOfDay();
        if (! $this->isWithinAssignment($date, $assignment)) {
            return response()->json(['error' => 'La data no pertany al període de la rutina'], 400);
        }

        $isFoodLog = $data['fieldName'] === 'food_log' && $assignment->template->foodLogEnabled;
        $fieldDef = $assignment->template->fields->firstWhere('name', $data['fieldName']);
        if (! $isFoodLog && ! $fieldDef) {
            return response()->json(['error' => 'El camp no pertany a la rutina'], 400);
        }
        if ($fieldDef) {
            $valueError = FieldValueValidator::validate($fieldDef->toArray(), $data['value']);
            if ($valueError) {
                return response()->json(['error' => $valueError], 400);
            }
        }

        $recordedBy = $request->user()->role === 'NUTRICIONISTA' ? 'NUTRICIONISTA' : 'PACIENT';

        $record = DailyRecord::updateOrCreate(
            ['assignmentId' => $data['assignmentId'], 'recordDate' => $date, 'fieldName' => $data['fieldName']],
            ['patientId' => $assignment->patientId, 'value' => $data['value'], 'notes' => $data['notes'] ?? null, 'recordedBy' => $recordedBy],
        );

        return response()->json($record, 201);
    }

    // Batch create records for a day (pacient, o el seu nutricionista en nom seu)
    public function batch(BatchRecordsRequest $request)
    {
        $data = $request->validated();
        [$assignment, $error] = $this->resolveAssignmentForWrite($request, $data['assignmentId']);
        if ($error) {
            return $error;
        }

        $date = Carbon::parse($data['recordDate'])->startOfDay();
        if (! $this->isWithinAssignment($date, $assignment)) {
            return response()->json(['error' => 'La data no pertany al període de la rutina'], 400);
        }

        $validFields = $assignment->template->fields->pluck('name')->all();
        if ($assignment->template->foodLogEnabled) {
            $validFields[] = 'food_log';
        }
        $names = collect($data['entries'])->pluck('fieldName');
        if ($names->some(fn ($name) => ! in_array($name, $validFields, true)) || $names->unique()->count() !== $names->count()) {
            return response()->json(['error' => 'Els camps han de pertànyer a la rutina i no es poden repetir'], 400);
        }

        $entryByName = $names->combine($data['entries'])->map(fn ($e) => $e['value'] ?? null);
        $missingRequired = $assignment->template->fields->filter(
            fn ($field) => $field->required && $this->isMissingValue($entryByName->get($field->name))
        );
        if ($missingRequired->isNotEmpty()) {
            return response()->json(['error' => 'Falten camps obligatoris: '.$missingRequired->pluck('label')->join(', ')], 400);
        }
        foreach ($assignment->template->fields as $field) {
            if (! $entryByName->has($field->name)) {
                continue;
            }
            $valueError = FieldValueValidator::validate($field->toArray(), $entryByName->get($field->name));
            if ($valueError) {
                return response()->json(['error' => $valueError], 400);
            }
        }

        $recordedBy = $request->user()->role === 'NUTRICIONISTA' ? 'NUTRICIONISTA' : 'PACIENT';

        $results = collect($data['entries'])->map(fn ($e) => DailyRecord::updateOrCreate(
            ['assignmentId' => $data['assignmentId'], 'recordDate' => $date, 'fieldName' => $e['fieldName']],
            ['patientId' => $assignment->patientId, 'value' => $e['value'], 'notes' => $e['notes'] ?? null, 'recordedBy' => $recordedBy],
        ));

        return response()->json($results, 201);
    }

    // Get records for assignment (both roles)
    public function forAssignment(Request $request, string $assignmentId)
    {
        $assignment = RoutineAssignment::with('patient')->find($assignmentId);
        if (! $assignment) {
            return response()->json(['error' => 'No trobat'], 404);
        }

        $user = $request->user();
        $isOwnerNutricionista = $user->role === 'NUTRICIONISTA' && $assignment->patient->nutricionistaId === $user->id;
        $isOwnerPacient = $user->role === 'PACIENT' && $assignment->patient->userId === $user->id;
        if (! $isOwnerNutricionista && ! $isOwnerPacient) {
            return response()->json(['error' => 'Accés denegat'], 403);
        }

        $records = DailyRecord::where('assignmentId', $assignment->id)
            ->orderBy('recordDate')->orderBy('fieldName')->get();

        if ($isOwnerNutricionista) {
            AccessLogger::log($user->id, $user->role, 'VIEW_RECORDS', 'RoutineAssignment', $assignment->id);
        }

        return response()->json($records);
    }
}
