<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePatientRequest;
use App\Http\Requests\SetPatientPasswordRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Models\Patient;
use App\Models\User;
use App\Support\Licenses;
use App\Support\AccessLogger;
use App\Support\ClinicalProgress;
use App\Support\Paged;
use App\Support\RoutineProgress;
use App\Support\UrlHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        // Les hores de les visites són hora de rellotge (vegeu AppointmentMessenger): es compara amb l'"ara" de Madrid.
        $now = \App\Support\AppointmentMessenger::nowWall();
        $next = $patient->appointments->first(fn ($a) => $a->startAt->gte($now));
        $past = $patient->appointments->filter(fn ($a) => $a->startAt->lt($now))->last();

        return [
            'nextAppointmentAt' => optional($next)->startAt,
            'nextAppointmentModality' => optional($next)->modality,
            'lastVisitAt' => optional($past)->startAt,
            'lastVisitModality' => optional($past)->modality,
        ];
    }

    // Pacients del nutricionista, PAGINATS al servidor (norma «Llistes llargues»): { data, page, perPage, total, hasMore, counts }.
    // Filtres: ?tab=amb|sense|tots (amb/sense alguna rutina ACTIVE), ?q= (nom, correu o edat), ?patientId=, ?sort=nom|acaba|comenca
    // (nom; els que acaben abans primer; els que han començat abans primer). `counts` són els totals de les tres pestanyes,
    // independents de la cerca. Cada pacient porta les seves rutines amb progrés, tendència i alertes (només els de la pàgina).
    public function index(Request $request)
    {
        $uid = $request->user()->id;
        $active = fn ($q) => $q->select(DB::raw(1))->from('reg_routine_assignments as ra')->whereColumn('ra.patientId', 'sys_patients.id')->where('ra.status', 'ACTIVE');

        $query = Patient::query()
            ->select('sys_patients.*')
            ->join('sys_users as u', 'u.id', '=', 'sys_patients.userId')
            ->where('sys_patients.nutricionistaId', $uid)
            ->selectSub(fn ($q) => $q->from('reg_routine_assignments as ra')->selectRaw('min(ra.endDate)')->whereColumn('ra.patientId', 'sys_patients.id')->where('ra.status', 'ACTIVE'), 'earliestActiveEnd')
            ->selectSub(fn ($q) => $q->from('reg_routine_assignments as ra')->selectRaw('min(ra.startDate)')->whereColumn('ra.patientId', 'sys_patients.id'), 'earliestStart');

        $counts = [
            'tots' => (clone $query)->count(),
            'amb' => (clone $query)->whereExists($active)->count(),
        ];
        $counts['sense'] = $counts['tots'] - $counts['amb'];

        $tab = $request->query('tab', 'tots');
        if ($tab === 'amb') {
            $query->whereExists($active);
        } elseif ($tab === 'sense') {
            $query->whereNotExists($active);
        }
        if ($request->filled('patientId')) {
            $query->where('sys_patients.id', $request->query('patientId'));
        }
        if ($request->filled('q')) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(trim($request->query('q')))).'%';
            $query->where(fn ($q) => $q
                ->whereRaw('lower(u.name) like ?', [$like])
                ->orWhereRaw('lower(u.email) like ?', [$like])
                ->orWhereRaw('cast(timestampdiff(year, sys_patients.birthDate, curdate()) as char) like ?', [$like]));
        }
        match ($request->query('sort', 'nom')) {
            'acaba' => $query->orderByRaw('earliestActiveEnd is null')->orderBy('earliestActiveEnd'),
            'comenca' => $query->orderByRaw('earliestStart is null')->orderBy('earliestStart'),
            default => $query,
        };
        $query->orderBy('u.name')->orderBy('sys_patients.id');
        $query->with([
            'user:id,name,email,phone',
            'assignments' => fn ($q) => $q->with(['template:id,name,description,durationDays,iconId', 'template.icon', 'template.fields', 'records:id,assignmentId,recordDate,fieldName,value']),
            'appointments' => fn ($q) => $q->confirmed()->orderBy('startAt'),
        ]);

        // Els alertCounts es calculen un cop per pàgina: Paged::of mapeja fila a fila, per això es pagina a mà.
        [$page, $perPage] = Paged::window($request);
        $total = (clone $query)->count();
        $patients = $query->forPage($page, $perPage)->get();
        $alertCounts = $this->alertCounts($patients->flatMap(fn ($patient) => $patient->assignments->pluck('id')));

        $result = $patients->map(function (Patient $patient) use ($alertCounts) {
            $array = $patient->toArray();
            unset($array['appointments'], $array['earliestActiveEnd'], $array['earliestStart']);
            $array['photoUrl'] = UrlHelper::toAbsoluteUrl($patient->photoUrl);
            $array = array_merge($array, $this->visitFields($patient));
            $array['assignments'] = $patient->assignments->map(function ($assignment) use ($alertCounts) {
                $data = $assignment->only(['id', 'patientId', 'templateId', 'startDate', 'endDate', 'status', 'customNotes', 'createdAt', 'updatedAt']);
                $data['template'] = $assignment->template;
                $data = array_merge($data, RoutineProgress::forAssignment($assignment));
                $keyFields = $assignment->template->fields->where('isKeyField', true);
                $data['trend'] = ClinicalProgress::of($assignment->startDate, $assignment->endDate, RoutineProgress::countedDates($assignment), $assignment->records, $keyFields);
                $data['alerts'] = $alertCounts[$assignment->id] ?? ['urgent' => 0, 'review' => 0];

                return $data;
            })->values();

            return $array;
        });

        return response()->json(Paged::envelope($result, $page, $perPage, $total) + ['counts' => $counts]);
    }

    // Llista LLEUGERA per als selectors de pacient (calendari, missatges, filtre de Pacients): només id, nom, correu i foto,
    // sense rutines ni registres. No és la llista de treball (aquesta és index, paginada).
    public function options(Request $request)
    {
        $rows = Patient::where('nutricionistaId', $request->user()->id)
            ->with('user:id,name,email')
            ->get()
            ->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'name' => $patient->user->name,
                'email' => $patient->user->email,
                'photoUrl' => UrlHelper::toAbsoluteUrl($patient->photoUrl),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json($rows);
    }

    // Crea un pacient (o hi afegeix una relació nova si l'email ja existeix com a pacient
    // amb un altre nutricionista): mai amb contrasenya — la posarà el propi pacient en el
    // primer login (veure POST /auth/set-password).
    public function store(CreatePatientRequest $request)
    {
        $data = $request->validated();
        $nutricionistaId = $request->user()->id;
        if ($limitError = Licenses::patientLimitError($request->user())) {
            return response()->json(['error' => $limitError, 'code' => 'PLAN_LIMIT'], 403);
        }
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
            'assignments.template.fields',
            'assignments.records' => fn ($q) => $q->orderBy('recordDate', 'desc'),
            'appointments' => fn ($q) => $q->confirmed()->orderBy('startAt'),
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

        // L'adherència/tendència es calculen amb TOTS els registres de l'assignació.
        $alertCounts = $this->alertCounts($patient->assignments->pluck('id'));
        $array['assignments'] = $patient->assignments->map(function ($assignment) use ($alertCounts) {
            $data = $assignment->toArray();
            $data['alerts'] = $alertCounts[$assignment->id] ?? ['urgent' => 0, 'review' => 0];
            $data = array_merge($data, RoutineProgress::forAssignment($assignment));
            $keyFields = $assignment->template->fields->where('isKeyField', true);
            $data['trend'] = ClinicalProgress::of($assignment->startDate, $assignment->endDate, RoutineProgress::countedDates($assignment), $assignment->records, $keyFields);
            // Els registres no s'envien (abans, retallats a 50 en silenci): la fitxa només necessita saber-ne el nombre, per
            // decidir si la rutina es pot eliminar. Els registres d'una rutina es llegeixen a /records/assignment/{id}.
            unset($data['records']);
            $data['recordsCount'] = $assignment->records->count();

            return $data;
        })->values();

        return response()->json($array);
    }

    // El nutricionista posa una contrasenya nova al seu pacient (sense la que té ara). Es tanquen les sessions obertes del pacient
    // perquè entri amb la nova.
    public function setPassword(SetPatientPasswordRequest $request, string $id)
    {
        $patient = Patient::with('user')->where('id', $id)->where('nutricionistaId', $request->user()->id)->first();
        if (! $patient || ! $patient->user || $patient->user->deletedAt) {
            return response()->json(['error' => 'Pacient no trobat'], 404);
        }

        $patient->user->update(['passwordHash' => Hash::make($request->validated('newPassword'))]);
        $patient->user->tokens()->delete();

        return response()->json(['message' => 'Contrasenya del pacient actualitzada correctament']);
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

    // Alertes pendents (obertes o vistes) per assignació i nivell: {assignmentId: {urgent, review}}.
    private function alertCounts($assignmentIds): array
    {
        $counts = [];
        foreach (\App\Models\Alert::whereIn('assignmentId', $assignmentIds->all())->whereIn('status', ['OPEN', 'SEEN'])
            ->selectRaw('assignmentId, level, count(*) as n')->groupBy('assignmentId', 'level')->get() as $row) {
            $counts[$row->assignmentId][$row->level === 'URGENT' ? 'urgent' : 'review'] = (int) $row->n;
        }

        return array_map(fn ($c) => ['urgent' => $c['urgent'] ?? 0, 'review' => $c['review'] ?? 0], $counts);
    }
}
