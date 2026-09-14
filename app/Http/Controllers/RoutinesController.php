<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignmentStatusRequest;
use App\Http\Requests\AssignRoutineRequest;
use App\Http\Requests\CreateRoutineTemplateRequest;
use App\Http\Requests\FieldLibraryItemRequest;
use App\Http\Requests\UpdateRoutineTemplateRequest;
use App\Models\FieldIcon;
use App\Models\FieldLibraryItem;
use App\Models\Food;
use App\Models\LibraryRoutine;
use App\Models\Patient;
use App\Models\RoutineAssignment;
use App\Models\RoutineField;
use App\Models\RoutineInstruction;
use App\Models\RoutineTemplate;
use App\Models\RoutineTemplateFood;
use App\Support\UrlHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RoutinesController extends Controller
{
    // Biblioteca clínica predefinida i catàleg d'aliments.
    public function library()
    {
        $routines = LibraryRoutine::with([
            'icon',
            'fields' => fn ($q) => $q->orderBy('orderIndex'),
            'instructions' => fn ($q) => $q->orderBy('orderIndex'),
            'foods.food.category',
        ])->orderBy('name')->get();

        return response()->json($routines);
    }

    // Catàleg d'aliments: el nutricionista el consulta per configurar rutines, i el
    // pacient (o el nutricionista en nom seu) per triar què ha menjat al registre d'àpats.
    public function foods()
    {
        $foods = Food::with('category')
            ->join('food_categories', 'foods.categoryId', '=', 'food_categories.id')
            ->orderBy('food_categories.name')
            ->orderBy('foods.name')
            ->select('foods.*')
            ->get();

        return response()->json($foods);
    }

    // Biblioteca de camps reutilitzables: globals (createdById null) + els propis del nutricionista.
    public function fieldLibraryIndex(Request $request)
    {
        $items = FieldLibraryItem::where(function ($q) use ($request) {
            $q->whereNull('createdById')->orWhere('createdById', $request->user()->id);
        })->orderByRaw('createdById IS NOT NULL')->orderBy('label')->get();

        return response()->json($items);
    }

    // Desa un camp propi a la biblioteca perquè es pugui reutilitzar en futures rutines.
    public function fieldLibraryStore(FieldLibraryItemRequest $request)
    {
        $item = FieldLibraryItem::create([...$request->validated(), 'createdById' => $request->user()->id]);

        return response()->json($item, 201);
    }

    // Elimina un camp propi de la biblioteca (els camps globals no es poden eliminar).
    public function fieldLibraryDestroy(Request $request, string $id)
    {
        $item = FieldLibraryItem::find($id);
        if (! $item) {
            return response()->json(['error' => 'No trobat'], 404);
        }
        if ($item->createdById !== $request->user()->id) {
            return response()->json(['error' => 'Accés denegat'], 403);
        }
        $item->delete();

        return response()->json(['message' => 'Eliminat']);
    }

    // Converteix una rutina de biblioteca en una plantilla privada editable.
    public function templateFromLibrary(Request $request, string $libraryId)
    {
        $library = LibraryRoutine::with(['fields', 'instructions', 'foods'])->find($libraryId);
        if (! $library) {
            return response()->json(['error' => 'Rutina de biblioteca no trobada'], 404);
        }
        $requestedName = trim((string) $request->input('name', ''));

        $template = DB::transaction(function () use ($library, $requestedName, $request) {
            $template = RoutineTemplate::create([
                'name' => $requestedName ?: $library->name,
                'description' => $library->description,
                'durationDays' => $library->durationDays,
                'objective' => $library->objective,
                'foodLogEnabled' => false,
                'createdById' => $request->user()->id,
                'iconId' => $library->iconId,
            ]);

            foreach ($library->fields as $field) {
                RoutineField::create([
                    'templateId' => $template->id,
                    'name' => $field->name,
                    'label' => $field->label,
                    'fieldType' => $field->fieldType,
                    'frequency' => $field->frequency,
                    'required' => $field->required,
                    'options' => $field->options,
                    'orderIndex' => $field->orderIndex,
                ]);
            }
            foreach ($library->instructions as $instruction) {
                RoutineInstruction::create([
                    'templateId' => $template->id,
                    'title' => $instruction->title,
                    'content' => $instruction->content,
                    'orderIndex' => $instruction->orderIndex,
                ]);
            }
            foreach ($library->foods as $food) {
                RoutineTemplateFood::create([
                    'templateId' => $template->id,
                    'foodId' => $food->foodId,
                    'use' => $food->use,
                    'note' => $food->note,
                ]);
            }

            return $template->fresh(['fields', 'instructions', 'foods.food.category']);
        });

        return response()->json($template, 201);
    }

    // List templates (own + public)
    public function templatesIndex(Request $request)
    {
        $templates = RoutineTemplate::where(fn ($q) => $q->where('createdById', $request->user()->id)->orWhere('isPublic', true))
            ->with(['fields' => fn ($q) => $q->orderBy('orderIndex'), 'foods.food.category'])
            ->orderBy('createdAt', 'desc')
            ->get();

        return response()->json($templates);
    }

    // Create template
    public function templatesStore(CreateRoutineTemplateRequest $request)
    {
        $data = $request->validated();

        $template = DB::transaction(function () use ($data, $request) {
            $template = RoutineTemplate::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'durationDays' => (int) $data['durationDays'],
                'objective' => $data['objective'] ?? null,
                'foodLogEnabled' => $data['foodLogEnabled'] ?? false,
                'isPublic' => $data['isPublic'] ?? false,
                'createdById' => $request->user()->id,
                // Rutina creada des de zero (no ve de la biblioteca): icona genèrica.
                'iconId' => FieldIcon::where('key', 'lib-nutricionista')->value('id'),
            ]);

            foreach (($data['fields'] ?? []) as $idx => $f) {
                RoutineField::create([
                    'templateId' => $template->id,
                    'name' => $f['name'] ?? "camp_$idx",
                    'label' => $f['label'] ?? $f['name'],
                    'fieldType' => $f['fieldType'] ?? 'TEXT',
                    'frequency' => $f['frequency'] ?? 'daily',
                    'required' => $f['required'] ?? true,
                    'options' => $f['options'] ?? null,
                    'orderIndex' => $f['orderIndex'] ?? $idx,
                    'fieldIconId' => $f['fieldIconId'] ?? null,
                    'goodDirection' => $f['goodDirection'] ?? null,
                ]);
            }
            foreach (($data['foods'] ?? []) as $f) {
                RoutineTemplateFood::create([
                    'templateId' => $template->id,
                    'foodId' => $f['foodId'],
                    'use' => $f['use'],
                    'note' => $f['note'] ?? null,
                ]);
            }

            return $template->fresh(['fields', 'foods.food.category']);
        });

        return response()->json($template, 201);
    }

    // Update template. Els camps només es reemplacen si arriben explícitament al cos.
    public function templatesUpdate(UpdateRoutineTemplateRequest $request, string $id)
    {
        $data = $request->validated();
        $existing = RoutineTemplate::find($id);
        if (! $existing) {
            return response()->json(['error' => 'Plantilla no trobada'], 404);
        }
        if ($existing->createdById !== $request->user()->id) {
            return response()->json(['error' => 'Accés denegat'], 403);
        }

        $template = DB::transaction(function () use ($data, $existing) {
            $existing->update(array_intersect_key($data, array_flip(['name', 'description', 'durationDays', 'objective', 'foodLogEnabled', 'isPublic'])));

            if (array_key_exists('fields', $data)) {
                RoutineField::where('templateId', $existing->id)->delete();
                foreach (($data['fields'] ?? []) as $idx => $f) {
                    RoutineField::create([
                        'templateId' => $existing->id,
                        'name' => $f['name'] ?? "camp_$idx",
                        'label' => $f['label'] ?? $f['name'] ?? ('Camp '.($idx + 1)),
                        'fieldType' => $f['fieldType'] ?? 'TEXT',
                        'frequency' => $f['frequency'] ?? 'daily',
                        'required' => $f['required'] ?? true,
                        'options' => $f['options'] ?? null,
                        'orderIndex' => $f['orderIndex'] ?? $idx,
                        'fieldIconId' => $f['fieldIconId'] ?? null,
                        'goodDirection' => $f['goodDirection'] ?? null,
                    ]);
                }
            }
            if (array_key_exists('foods', $data)) {
                RoutineTemplateFood::where('templateId', $existing->id)->delete();
                foreach (($data['foods'] ?? []) as $f) {
                    RoutineTemplateFood::create([
                        'templateId' => $existing->id,
                        'foodId' => $f['foodId'],
                        'use' => $f['use'],
                        'note' => $f['note'] ?? null,
                    ]);
                }
            }

            return $existing->fresh(['fields' => fn ($q) => $q->orderBy('orderIndex'), 'foods.food.category']);
        });

        return response()->json($template);
    }

    // Assign routine to patient
    public function assign(AssignRoutineRequest $request)
    {
        $data = $request->validated();

        $patient = Patient::where('id', $data['patientId'])->where('nutricionistaId', $request->user()->id)->first();
        if (! $patient) {
            return response()->json(['error' => 'Pacient no trobat'], 404);
        }
        $template = RoutineTemplate::where('id', $data['templateId'])
            ->where(fn ($q) => $q->where('createdById', $request->user()->id)->orWhere('isPublic', true))
            ->first();
        if (! $template) {
            return response()->json(['error' => 'Plantilla no trobada'], 404);
        }

        $start = Carbon::parse($data['startDate']);
        $end = $start->copy()->addDays($template->durationDays - 1);

        $assignment = RoutineAssignment::create([
            'patientId' => $data['patientId'],
            'templateId' => $data['templateId'],
            'startDate' => $start,
            'endDate' => $end,
            'status' => 'ACTIVE',
            'customNotes' => $data['customNotes'] ?? null,
        ]);
        $assignment->load(['template.fields', 'patient.user:id,name,email']);

        return response()->json($assignment, 201);
    }

    // Get active assignment for current patient. Un pacient pot tenir més d'un nutricionista
    // (i per tant més d'una fila Patient); aquest endpoint no ho distingeix, agafa la primera.
    public function myActive(Request $request)
    {
        $patient = Patient::where('userId', $request->user()->id)->first();
        if (! $patient) {
            return response()->json(['error' => 'Perfil de pacient no trobat'], 404);
        }

        $assignment = RoutineAssignment::where('patientId', $patient->id)
            ->where('status', 'ACTIVE')
            ->with([
                'template.fields' => fn ($q) => $q->orderBy('orderIndex'),
                'template.foods.food.category',
                'records' => fn ($q) => $q->whereDate('recordDate', '>=', now()->toDateString()),
            ])
            ->orderBy('startDate', 'desc')
            ->first();

        return response()->json($assignment);
    }

    // List assignments of a patient (for nutricionista)
    public function assignmentsOfPatient(Request $request, string $patientId)
    {
        $patient = Patient::where('id', $patientId)->where('nutricionistaId', $request->user()->id)->first();
        if (! $patient) {
            return response()->json(['error' => 'Pacient no trobat'], 404);
        }

        $assignments = RoutineAssignment::where('patientId', $patient->id)
            ->with(['template.fields', 'records' => fn ($q) => $q->orderBy('recordDate', 'desc')])
            ->orderBy('startDate', 'desc')
            ->get();

        // Limitem a 100 registres per assignació en PHP (no amb ->limit() a l'eager load): veure
        // el comentari a PatientsController::show sobre la incompatibilitat amb MariaDB.
        $assignments->each(fn ($a) => $a->setRelation('records', $a->records->take(100)));

        return response()->json($assignments);
    }

    // Llista totes les assignacions del pacient actual, de tots els nutricionistes als
    // quals estigui associat (una fila Patient per nutricionista). Cada assignació porta
    // les dades del nutricionista corresponent perquè el frontend les pugui agrupar en pestanyes.
    public function myAssignments(Request $request)
    {
        $assignments = RoutineAssignment::whereHas('patient', fn ($q) => $q->where('userId', $request->user()->id))
            ->with([
                'patient:id,nutricionistaId',
                'patient.nutricionista:id,name',
                'patient.nutricionista.nutricionistaProfile:userId,companyName,logoUrl',
                'template.fields' => fn ($q) => $q->orderBy('orderIndex'),
                'template.foods.food.category',
                'records:id,assignmentId,recordDate,fieldName',
            ])
            ->orderBy('startDate', 'desc')
            ->get();

        $today = Carbon::now()->startOfDay();

        $result = $assignments->map(function (RoutineAssignment $a) use ($today) {
            $start = Carbon::parse($a->startDate)->startOfDay();
            $end = Carbon::parse($a->endDate)->startOfDay();

            $computedStatus = $a->status;
            if ($a->status === 'ACTIVE') {
                $computedStatus = $today->lt($start) ? 'PREPARADA' : ($today->gt($end) ? 'ACABADA' : 'ACTIVA');
            }

            $daysWithRecords = $a->records->map(fn ($r) => Carbon::parse($r->recordDate)->toDateString())->unique()->count();

            $array = $a->toArray();
            $array['nutricionistaId'] = $a->patient->nutricionistaId;
            $array['nutricionista'] = [
                'id' => $a->patient->nutricionista->id,
                'name' => $a->patient->nutricionista->name,
                'companyName' => $a->patient->nutricionista->nutricionistaProfile?->companyName,
                'logoUrl' => UrlHelper::toAbsoluteUrl($a->patient->nutricionista->nutricionistaProfile?->logoUrl),
            ];
            $array['computedStatus'] = $computedStatus;
            $array['daysWithRecords'] = $daysWithRecords;

            return $array;
        });

        return response()->json($result);
    }

    // ========== CANVIAR ESTAT D'UNA ASSIGNACIÓ ==========
    public function updateStatus(AssignmentStatusRequest $request, string $id)
    {
        $assignment = RoutineAssignment::with('patient')->find($id);
        if (! $assignment) {
            return response()->json(['error' => 'Assignació no trobada'], 404);
        }
        if ($assignment->patient->nutricionistaId !== $request->user()->id) {
            return response()->json(['error' => 'Accés denegat'], 403);
        }

        $status = $request->validated('status');
        if ($status === 'COMPLETED' && ! $request->validated('evolutionRating')) {
            return response()->json(['error' => 'Cal indicar com ha evolucionat el pacient per completar la rutina.'], 422);
        }

        $assignment->update([
            'status' => $status,
            'evolutionRating' => $status === 'COMPLETED' ? $request->validated('evolutionRating') : $assignment->evolutionRating,
        ]);
        $assignment->load(['template:id,name,durationDays', 'patient.user:id,name,email']);

        return response()->json($assignment);
    }

    // Elimina una assignació de rutina. Només si no té cap registre de dades entrat
    // (si en té, s'ha de completar/cancel·lar, no eliminar, per no perdre historial clínic).
    public function destroyAssignment(Request $request, string $id)
    {
        $assignment = RoutineAssignment::with('patient')->find($id);
        if (! $assignment) {
            return response()->json(['error' => 'Assignació no trobada'], 404);
        }
        if ($assignment->patient->nutricionistaId !== $request->user()->id) {
            return response()->json(['error' => 'Accés denegat'], 403);
        }
        if ($assignment->records()->exists()) {
            return response()->json(['error' => 'No es pot eliminar una rutina amb registres de dades. Completa-la o cancel·la-la.'], 409);
        }

        $assignment->delete();

        return response()->json(['message' => 'Rutina eliminada correctament']);
    }
}
