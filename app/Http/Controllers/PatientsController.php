<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Models\Patient;
use App\Models\User;
use App\Support\AccessLogger;
use App\Support\AssignmentTrend;
use App\Support\RoutineProgress;
use App\Support\UrlHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PatientsController extends Controller
{
    private const ALLOWED_PHOTO_MIME = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    private function deletePhotoFile(?string $photoUrl): void
    {
        if (! $photoUrl) {
            return;
        }
        Storage::disk('public')->delete($photoUrl);
    }

    // 'Última visita' i 'Pròxima cita' es calculen a partir de les cites reals (calendari):
    // la cita passada més recent i la futura més propera, respectivament.
    private function visitFields(Patient $patient): array
    {
        $now = now();
        $next = $patient->appointments->first(fn ($a) => $a->startAt->gte($now));
        $past = $patient->appointments->filter(fn ($a) => $a->startAt->lt($now))->last();

        return [
            'nextAppointmentAt' => optional($next)->startAt,
            'lastVisitAt' => optional($past)->startAt,
        ];
    }

    // List patients of nutricionista
    public function index(Request $request)
    {
        $patients = Patient::where('nutricionistaId', $request->user()->id)
            ->with([
                'user:id,name,email,phone',
                'assignments' => fn ($q) => $q->with(['template:id,name,description,durationDays,iconId', 'template.icon', 'records:id,assignmentId,recordDate']),
                'appointments' => fn ($q) => $q->orderBy('startAt'),
            ])
            ->orderBy('createdAt', 'desc')
            ->get();

        $result = $patients->map(function (Patient $patient) {
            $array = $patient->toArray();
            unset($array['appointments']);
            $array['photoUrl'] = UrlHelper::toAbsoluteUrl($patient->photoUrl);
            $array = array_merge($array, $this->visitFields($patient));
            $array['assignments'] = $patient->assignments->map(function ($assignment) {
                $data = $assignment->only(['id', 'patientId', 'templateId', 'startDate', 'endDate', 'status', 'customNotes', 'createdAt', 'updatedAt']);
                $data['template'] = $assignment->template;
                $data = array_merge($data, RoutineProgress::of($assignment->startDate, $assignment->endDate, $assignment->records->pluck('recordDate')));
                $data['trend'] = AssignmentTrend::of($assignment->startDate, $assignment->endDate, $assignment->records->pluck('recordDate'));

                return $data;
            })->values();

            return $array;
        });

        return response()->json($result);
    }

    // Crea un pacient (o hi afegeix una relació nova si l'email ja existeix com a pacient
    // amb un altre nutricionista): mai amb contrasenya — la posarà el propi pacient en el
    // primer login (veure POST /auth/set-password).
    public function store(CreatePatientRequest $request)
    {
        $data = $request->validated();
        $nutricionistaId = $request->user()->id;
        $existing = User::where('email', $data['email'])->first();

        if ($existing) {
            if ($existing->role !== 'PACIENT') {
                return response()->json(['error' => 'Aquest email ja pertany a un altre tipus de compte'], 409);
            }
            $alreadyLinked = Patient::where('userId', $existing->id)->where('nutricionistaId', $nutricionistaId)->exists();
            if ($alreadyLinked) {
                return response()->json(['error' => "Aquest pacient ja està donat d'alta amb tu"], 409);
            }

            $patient = Patient::create([
                'userId' => $existing->id,
                'nutricionistaId' => $nutricionistaId,
                'birthDate' => $data['birthDate'] ?? null,
                'gender' => $data['gender'] ?? 'NO_DEFINIT',
                'notes' => $data['notes'] ?? null,
            ]);
            $patient->load('user:id,name,email,phone');

            return response()->json($patient, 201);
        }

        $patient = null;
        DB::transaction(function () use ($data, $nutricionistaId, &$patient) {
            $user = User::create([
                'email' => $data['email'],
                'passwordHash' => null,
                'name' => $data['name'],
                'role' => 'PACIENT',
                'phone' => $data['phone'] ?? null,
            ]);

            $patient = Patient::create([
                'userId' => $user->id,
                'nutricionistaId' => $nutricionistaId,
                'birthDate' => $data['birthDate'] ?? null,
                'gender' => $data['gender'] ?? 'NO_DEFINIT',
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $patient->load('user:id,name,email,phone');

        return response()->json($patient, 201);
    }

    // El propi pacient consulta les seves dades a cada nutricionista al qual pertany
    // (mai les notes, que són privades del nutricionista — només visibles per ell).
    public function myProfiles(Request $request)
    {
        $profiles = Patient::where('userId', $request->user()->id)
            ->with('nutricionista:id,name')
            ->orderBy('createdAt', 'asc')
            ->get(['id', 'nutricionistaId', 'birthDate', 'gender', 'photoUrl', 'createdAt']);

        $result = $profiles->map(fn (Patient $p) => [
            'id' => $p->id,
            'nutricionistaId' => $p->nutricionistaId,
            'birthDate' => $p->birthDate,
            'gender' => $p->gender,
            'nutricionistaName' => $p->nutricionista->name,
            'photoUrl' => UrlHelper::toAbsoluteUrl($p->photoUrl),
        ]);

        return response()->json($result);
    }

    // Get one patient
    public function show(Request $request, string $id)
    {
        $patient = Patient::with([
            'user:id,name,email,phone',
            'assignments.template.icon',
            'assignments.records' => fn ($q) => $q->orderBy('recordDate', 'desc'),
            'appointments' => fn ($q) => $q->orderBy('startAt'),
        ])->find($id);

        if (! $patient) {
            return response()->json(['error' => 'Pacient no trobat'], 404);
        }

        $user = $request->user();
        $isOwnerNutricionista = $user->role === 'NUTRICIONISTA' && $patient->nutricionistaId === $user->id;
        $isOwnerPacient = $user->role === 'PACIENT' && $patient->userId === $user->id;
        if (! $isOwnerNutricionista && ! $isOwnerPacient) {
            return response()->json(['error' => 'Accés denegat'], 403);
        }

        if ($isOwnerNutricionista) {
            AccessLogger::log($user->id, $user->role, 'VIEW_PATIENT', 'Patient', $patient->id);
        }

        $array = $patient->toArray();
        unset($array['appointments']);
        if ($isOwnerPacient) {
            unset($array['notes']); // Les notes són privades del nutricionista
        }
        $array['photoUrl'] = UrlHelper::toAbsoluteUrl($patient->photoUrl);
        $array = array_merge($array, $this->visitFields($patient));

        // L'adherència/tendència es calculen amb TOTS els registres (abans de limitar-los a
        // 50 per a la vista): si es fes amb la llista ja retallada, una assignació amb més de
        // 50 registres sortiria amb l'adherència infravalorada.
        $array['assignments'] = $patient->assignments->map(function ($assignment) {
            $data = $assignment->toArray();
            $data = array_merge($data, RoutineProgress::of($assignment->startDate, $assignment->endDate, $assignment->records->pluck('recordDate')));
            $data['trend'] = AssignmentTrend::of($assignment->startDate, $assignment->endDate, $assignment->records->pluck('recordDate'));
            // Limitem a 50 registres per assignació en PHP (no amb ->limit() a l'eager load): Eloquent
            // implementaria el límit per relació amb ROW_NUMBER() OVER(...), que MariaDB (usat en
            // local) no gestiona bé combinat amb aquesta subconsulta ("Mixing of GROUP columns...").
            $data['records'] = $assignment->records->take(50)->values();

            return $data;
        })->values();

        return response()->json($array);
    }

    // Update patient: actualitza dades d'usuari + perfil de pacient
    public function update(UpdatePatientRequest $request, string $id)
    {
        $data = $request->validated();
        $patient = Patient::with('user')->find($id);

        if (! $patient) {
            return response()->json(['error' => 'Pacient no trobat'], 404);
        }
        if ($patient->nutricionistaId !== $request->user()->id) {
            return response()->json(['error' => 'Accés denegat'], 403);
        }

        if (isset($data['email']) && $data['email'] !== $patient->user->email && User::where('email', $data['email'])->exists()) {
            return response()->json(['error' => 'Email ja en ús'], 409);
        }

        DB::transaction(function () use ($data, $patient) {
            $patient->user->update(array_intersect_key($data, array_flip(['name', 'email', 'phone'])));
            $patient->update(array_intersect_key($data, array_flip(['birthDate', 'gender', 'notes'])));
        });

        $patient->load('user:id,name,email,phone');

        return response()->json($patient);
    }

    // Puja o reemplaça la foto del pacient
    public function uploadPhoto(Request $request, string $id)
    {
        $patient = Patient::find($id);
        if (! $patient) {
            return response()->json(['error' => 'Pacient no trobat'], 404);
        }
        if ($patient->nutricionistaId !== $request->user()->id) {
            return response()->json(['error' => 'Accés denegat'], 403);
        }

        $file = $request->file('photo');
        if (! $file) {
            return response()->json(['error' => 'Cap fitxer rebut'], 400);
        }
        $ext = self::ALLOWED_PHOTO_MIME[$file->getMimeType()] ?? null;
        if (! $ext) {
            return response()->json(['error' => "Format d'imatge no vàlid. Usa PNG, JPG o WEBP."], 400);
        }
        if ($file->getSize() > 2 * 1024 * 1024) {
            return response()->json(['error' => 'La imatge no pot superar els 2 MB'], 400);
        }

        $this->deletePhotoFile($patient->photoUrl);
        $filename = "{$patient->id}-".now()->getTimestampMs().'.'.$ext;
        $path = $file->storeAs('patient-photos', $filename, 'public');

        $patient->update(['photoUrl' => $path]);

        return response()->json(['photoUrl' => UrlHelper::toAbsoluteUrl($path)]);
    }

    // Elimina la foto del pacient
    public function deletePhoto(Request $request, string $id)
    {
        $patient = Patient::find($id);
        if (! $patient) {
            return response()->json(['error' => 'Pacient no trobat'], 404);
        }
        if ($patient->nutricionistaId !== $request->user()->id) {
            return response()->json(['error' => 'Accés denegat'], 403);
        }
        if (! $patient->photoUrl) {
            return response()->json(['message' => 'No hi havia cap foto']);
        }

        $this->deletePhotoFile($patient->photoUrl);
        $patient->update(['photoUrl' => null]);

        return response()->json(['message' => 'Foto eliminada']);
    }
}
