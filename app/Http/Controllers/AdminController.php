<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminCreateUserRequest;
use App\Http\Requests\AdminGrantLicenseRequest;
use App\Http\Requests\AdminUpdateUserRequest;
use App\Models\AccessLog;
use App\Models\Patient;
use App\Models\RoutineTemplate;
use App\Models\User;
use App\Support\Invoices;
use App\Support\Licenses;
use App\Support\Paged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    private function userWith()
    {
        return User::with([
            'patientProfiles:id,userId,nutricionistaId,birthDate,notes,createdAt',
            'patientProfiles.nutricionista:id,name,email',
            'licenses',
        ]);
    }

    // Afegeix `plan` ({code: EvoPro|EvoDemo, until, indefinite}) als nutricionistes d'un usuari o d'una col·lecció
    // (les llicències ja venen carregades amb userWith) i amaga l'historial en brut.
    private function withPlan($users)
    {
        foreach ($users instanceof User ? [$users] : $users as $u) {
            if ($u->role === 'NUTRICIONISTA') {
                $u->setAttribute('plan', Licenses::summary($u->licenses));
            }
            $u->makeHidden('licenses');
        }

        return $users;
    }

    // Llista els usuaris actius de primer nivell (administradors i nutricionistes), PAGINADA al servidor (norma «Llistes llargues»):
    // { data, page, perPage, total, hasMore, deletedCount }. ?role=ALL (per defecte: administradors i, després, nutricionistes) |
    // ADMIN | NUTRICIONISTA. Cada nutricionista porta `plan` i `patientsCount`; els seus pacients es demanen a part
    // (AdminNutricionistaController::patients). Els usuaris eliminats (anonimitzats) es consulten a /users/deleted, sense barrejar-los.
    public function index(Request $request)
    {
        $role = $request->query('role', 'ALL');
        $query = User::query()
            ->select('sys_users.*')
            ->selectSub(fn ($q) => $q->from('sys_patients')->join('sys_users as pu', 'pu.id', '=', 'sys_patients.userId')->whereNull('pu.deletedAt')->selectRaw('count(*)')->whereColumn('sys_patients.nutricionistaId', 'sys_users.id'), 'patientsCount')
            ->with('licenses')
            ->whereNull('deletedAt')
            ->whereIn('role', in_array($role, ['ADMIN', 'NUTRICIONISTA'], true) ? [$role] : ['ADMIN', 'NUTRICIONISTA'])
            ->orderBy('role')->orderBy('name')->orderBy('sys_users.id');

        return response()->json(Paged::of($query, $request, fn (User $user) => $this->withPlan($user)->toArray(), 15) + [
            'deletedCount' => User::whereNotNull('deletedAt')->count(),
        ]);
    }

    // Comptadors del panell d'inici de l'administrador (sobre els usuaris actius), amb les altes dels últims 15 dies:
    // { total, admin, nutri, pacient, multiNutri } i la mateixa estructura a `new`. Sense baixar cap usuari.
    public function stats()
    {
        $cutoff = now()->subDays(15);
        $rows = User::whereNull('deletedAt')->selectRaw('role, count(*) as n, sum(createdAt >= ?) as recent', [$cutoff])->groupBy('role')->get()->keyBy('role');
        $of = fn (string $role, string $field) => (int) ($rows[$role]->{$field} ?? 0);

        // Pacients amb més d'un nutricionista; «nous» si algun dels seus vincles és dels últims 15 dies.
        $multi = DB::query()->fromSub(
            DB::table('sys_patients')->join('sys_users', 'sys_users.id', '=', 'sys_patients.userId')->whereNull('sys_users.deletedAt')
                ->groupBy('sys_patients.userId')->havingRaw('count(*) > 1')->selectRaw('sys_patients.userId, max(sys_patients.createdAt) as latest'),
            'm'
        )->selectRaw('count(*) as n, coalesce(sum(latest >= ?), 0) as recent', [$cutoff])->first();

        return response()->json([
            'total' => (int) $rows->sum('n'),
            'admin' => $of('ADMIN', 'n'),
            'nutri' => $of('NUTRICIONISTA', 'n'),
            'pacient' => $of('PACIENT', 'n'),
            'multiNutri' => (int) $multi->n,
            'new' => [
                'total' => (int) $rows->sum('recent'),
                'admin' => $of('ADMIN', 'recent'),
                'nutri' => $of('NUTRICIONISTA', 'recent'),
                'pacient' => $of('PACIENT', 'recent'),
                'multiNutri' => (int) $multi->recent,
            ],
        ]);
    }

    // Llista els usuaris eliminats (anonimitzats), PAGINADA al servidor, els últims eliminats primer.
    public function deleted(Request $request)
    {
        $query = $this->userWith()->whereNotNull('deletedAt')->orderBy('deletedAt', 'desc')->orderBy('id');

        return response()->json(Paged::of($query, $request, fn (User $user) => $this->withPlan($user)->toArray(), 15));
    }

    // Llista només nutricionistes (útil per al selector en crear/reassignar pacient)
    public function nutricionistes()
    {
        $list = User::where('role', 'NUTRICIONISTA')->whereNull('deletedAt')->orderBy('name')->get(['id', 'name', 'email']);

        return response()->json($list);
    }

    // Detall d'un usuari (per a la pantalla d'edició)
    public function show(string $id)
    {
        $user = $this->userWith()->find($id);
        if (! $user) {
            return response()->json(['error' => 'Usuari no trobat'], 404);
        }

        return response()->json($this->withPlan($user));
    }

    // Passa un nutricionista a EvoPro fins a la data indicada (inclosa). Revoca la llicència vigent i en crea una
    // de nova (source ADMIN), així la data indicada és la que mana, sigui més llunyana o més propera.
    public function grantLicense(AdminGrantLicenseRequest $request, string $id)
    {
        $target = User::where('role', 'NUTRICIONISTA')->whereNull('deletedAt')->find($id);
        if (! $target) {
            return response()->json(['error' => 'Nutricionista no trobat'], 404);
        }
        $data = $request->validated();
        $license = Licenses::grant($target, $data['endsAt'], 'ADMIN', $request->user()->id, [
            'note' => $data['note'] ?? null,
            'billingPeriod' => $data['billingPeriod'] ?? 'MANUAL',
            'amountCents' => $data['amountCents'] ?? null,
            'currency' => isset($data['amountCents']) ? 'EUR' : null,
        ]);
        // Amb import cobrat, la factura s'emet i s'envia al nutricionista com un pagament més.
        if (! empty($data['amountCents'])) {
            Invoices::ensureForLicense($license);
        }

        return response()->json($this->withPlan($this->userWith()->find($id)));
    }

    // Passa un nutricionista a EvoDemo: revoca les llicències vigents (queden a l'historial).
    public function revokeLicense(string $id)
    {
        $target = User::where('role', 'NUTRICIONISTA')->whereNull('deletedAt')->find($id);
        if (! $target) {
            return response()->json(['error' => 'Nutricionista no trobat'], 404);
        }
        Licenses::revokeActive($target);

        return response()->json($this->withPlan($this->userWith()->find($id)));
    }

    // Crear usuari (ADMIN, NUTRICIONISTA o PACIENT). Un PACIENT mai es crea amb contrasenya
    // (la posa ell mateix al primer login); si l'email ja existeix com a PACIENT, s'hi afegeix
    // una relació nova amb aquest nutricionista en lloc de donar error.
    public function store(AdminCreateUserRequest $request)
    {
        $data = $request->validated();
        $existing = User::where('email', $data['email'])->first();

        if ($data['role'] === 'PACIENT') {
            $nutri = User::where('id', $data['nutricionistaId'])->where('role', 'NUTRICIONISTA')->whereNull('deletedAt')->first();
            if (! $nutri) {
                return response()->json(['error' => 'Nutricionista no vàlid'], 400);
            }

            if ($existing) {
                if ($existing->role !== 'PACIENT') {
                    return response()->json(['error' => 'Aquest email ja pertany a un altre tipus de compte'], 409);
                }
                $alreadyLinked = Patient::where('userId', $existing->id)->where('nutricionistaId', $data['nutricionistaId'])->exists();
                if ($alreadyLinked) {
                    return response()->json(['error' => "Aquest pacient ja està donat d'alta amb aquest nutricionista"], 409);
                }
                Patient::create([
                    'userId' => $existing->id,
                    'nutricionistaId' => $data['nutricionistaId'],
                    'birthDate' => $data['birthDate'] ?? null,
                    'gender' => $data['gender'] ?? 'NO_DEFINIT',
                    'notes' => $data['notes'] ?? null,
                ]);

                return response()->json(['id' => $existing->id, 'email' => $existing->email, 'name' => $existing->name, 'role' => $existing->role], 201);
            }

            $user = DB::transaction(function () use ($data) {
                $created = User::create([
                    'email' => $data['email'], 'passwordHash' => null, 'name' => $data['name'], 'role' => $data['role'], 'phone' => $data['phone'] ?? null,
                ]);
                Patient::create([
                    'userId' => $created->id,
                    'nutricionistaId' => $data['nutricionistaId'],
                    'birthDate' => $data['birthDate'] ?? null,
                    'gender' => $data['gender'] ?? 'NO_DEFINIT',
                    'notes' => $data['notes'] ?? null,
                ]);

                return $created;
            });

            return response()->json(['id' => $user->id, 'email' => $user->email, 'name' => $user->name, 'role' => $user->role], 201);
        }

        // ADMIN / NUTRICIONISTA: contrasenya sempre obligatòria (ja validat per l'schema), email únic
        if ($existing) {
            return response()->json(['error' => 'Aquest email ja està registrat'], 409);
        }
        $result = User::create([
            'email' => $data['email'], 'passwordHash' => Hash::make($data['password']), 'name' => $data['name'], 'role' => $data['role'], 'phone' => $data['phone'] ?? null,
        ]);

        return response()->json(['id' => $result->id, 'email' => $result->email, 'name' => $result->name, 'role' => $result->role], 201);
    }

    // Editar usuari existent: dades bàsiques + (si és pacient) reassignar nutricionista.
    // El canvi de ROL es bloqueja aquí a propòsit si l'usuari ja té dades vinculades
    // (perfil de pacient o plantilles creades), per evitar deixar dades òrfenes.
    public function update(AdminUpdateUserRequest $request, string $id)
    {
        $data = $request->validated();
        $target = User::with('patientProfiles')->find($id);
        if (! $target) {
            return response()->json(['error' => 'Usuari no trobat'], 404);
        }
        if ($target->deletedAt) {
            return response()->json(['error' => 'Aquest usuari està eliminat'], 409);
        }

        if (isset($data['email']) && $data['email'] !== $target->email && User::where('email', $data['email'])->exists()) {
            return response()->json(['error' => 'Email ja en ús'], 409);
        }

        $isRoleChange = array_key_exists('role', $data) && $data['role'] !== $target->role;

        if ($isRoleChange) {
            $templatesCount = RoutineTemplate::where('createdById', $id)->count();
            $patientsManaged = Patient::where('nutricionistaId', $id)->count();
            if ($target->patientProfiles->isNotEmpty() || $templatesCount > 0 || $patientsManaged > 0) {
                return response()->json(['error' => 'No es pot canviar el rol: aquest usuari ja té dades vinculades (perfil de pacient, plantilles creades o pacients assignats).'], 409);
            }
            if ($data['role'] === 'PACIENT' && empty($data['nutricionistaId'])) {
                return response()->json(['error' => 'Cal indicar un nutricionista per al nou pacient'], 400);
            }
        }

        // Reassignació de nutricionista d'un pacient ja existent (no és un canvi de rol).
        // Només té sentit quan l'usuari té exactament una relació de pacient: amb 0 no n'hi ha
        // cap a reassignar, i amb >1 caldria saber quina de les relacions es vol canviar (el
        // formulari d'edició d'admin no ho demana, queda fora d'abast per ara).
        if (! $isRoleChange && array_key_exists('nutricionistaId', $data)) {
            if ($target->patientProfiles->count() !== 1) {
                return response()->json(['error' => 'Aquest usuari no té exactament un nutricionista a reassignar'], 400);
            }
        }

        if (array_key_exists('nutricionistaId', $data)) {
            $nutri = User::where('id', $data['nutricionistaId'])->where('role', 'NUTRICIONISTA')->whereNull('deletedAt')->first();
            if (! $nutri) {
                return response()->json(['error' => 'Nutricionista no vàlid'], 400);
            }
        }

        DB::transaction(function () use ($data, $target, $isRoleChange, $id) {
            $target->update([
                ...array_intersect_key($data, array_flip(['name', 'email', 'phone'])),
                ...($isRoleChange ? ['role' => $data['role']] : []),
            ]);

            if ($isRoleChange && $data['role'] === 'PACIENT') {
                Patient::create(['userId' => $id, 'nutricionistaId' => $data['nutricionistaId']]);
            } elseif (! $isRoleChange && array_key_exists('nutricionistaId', $data)) {
                $target->patientProfiles->first()->update(['nutricionistaId' => $data['nutricionistaId']]);
            }
        });

        return response()->json($this->withPlan($this->userWith()->find($id)));
    }

    // Eliminar usuari (soft delete / anonimització, mateix criteri que DELETE /auth/me)
    public function destroy(Request $request, string $id)
    {
        if ($id === $request->user()->id) {
            return response()->json(['error' => 'Per eliminar el teu propi compte, fes-ho des de Configuració, no des de l\'administració.'], 400);
        }

        $target = User::find($id);
        if (! $target) {
            return response()->json(['error' => 'Usuari no trobat'], 404);
        }
        if ($target->deletedAt) {
            return response()->json(['error' => 'Aquest usuari ja està eliminat'], 409);
        }

        // Un nutricionista amb pacients assignats no es pot eliminar directament:
        // cal reassignar els pacients primer (evita deixar-los sense professional).
        if ($target->role === 'NUTRICIONISTA') {
            $activePatients = Patient::where('nutricionistaId', $id)->count();
            if ($activePatients > 0) {
                return response()->json(['error' => "Aquest nutricionista té $activePatients pacient(s) assignats. Reassigna'ls a un altre nutricionista abans d'eliminar-lo."], 409);
            }
        }

        // Evitem quedar-nos sense cap administrador
        if ($target->role === 'ADMIN') {
            $otherAdmins = User::where('role', 'ADMIN')->whereNull('deletedAt')->where('id', '!=', $id)->count();
            if ($otherAdmins === 0) {
                return response()->json(['error' => 'No es pot eliminar l\'últim administrador del sistema'], 409);
            }
        }

        $target->update([
            'name' => 'Usuari eliminat',
            'email' => "deleted-{$id}@anonymized.local",
            'phone' => null,
            'passwordHash' => 'DISABLED',
            'deletedAt' => now(),
        ]);
        $target->tokens()->delete();

        return response()->json(['message' => 'Usuari eliminat correctament']);
    }

    // Auditoria d'accessos a dades clíniques (RGPD): qui ha vist quin pacient/registre i quan. PAGINADA al servidor (norma
    // «Llistes llargues»): { data, page, perPage, total, hasMore }, els més recents primer.
    // Filtres: ?from=YYYY-MM-DD&to=YYYY-MM-DD (dates, ambdues incloses) i ?q= (part del nom o del correu de l'usuari).
    public function accessLogs(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $query = AccessLog::with('user:id,name,email,role')
            ->when($data['from'] ?? null, fn ($q, $from) => $q->where('createdAt', '>=', $from.' 00:00:00'))
            ->when($data['to'] ?? null, fn ($q, $to) => $q->where('createdAt', '<=', $to.' 23:59:59'))
            ->when(trim($data['q'] ?? '') !== '', function ($q) use ($data) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(trim($data['q']))).'%';
                $q->whereHas('user', fn ($u) => $u->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(email) like ?', [$like]));
            })
            ->orderBy('createdAt', 'desc')->orderBy('id');

        return response()->json(Paged::of($query, $request, fn (AccessLog $log) => $log->toArray(), 25));
    }
}
