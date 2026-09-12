<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminCreateUserRequest;
use App\Http\Requests\AdminUpdateUserRequest;
use App\Models\AccessLog;
use App\Models\Patient;
use App\Models\RoutineTemplate;
use App\Models\User;
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
        ]);
    }

    // Llista els usuaris actius (per defecte). Els usuaris eliminats (anonimitzats)
    // es consulten a part a /users/deleted, per no comptar-los ni mostrar-los barrejats.
    public function index()
    {
        $users = $this->userWith()->whereNull('deletedAt')->orderBy('role')->orderBy('name')->get();

        return response()->json($users);
    }

    // Llista els usuaris eliminats (anonimitzats)
    public function deleted()
    {
        $users = $this->userWith()->whereNotNull('deletedAt')->orderBy('deletedAt', 'desc')->get();

        return response()->json($users);
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

        return response()->json($user);
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

        return response()->json($this->userWith()->find($id));
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

    // Auditoria d'accessos a dades clíniques (RGPD): qui ha vist quin pacient/registre i quan
    public function accessLogs(Request $request)
    {
        $take = min((int) $request->query('take', 100), 500);
        $logs = AccessLog::with('user:id,name,email,role')->orderBy('createdAt', 'desc')->limit($take)->get();

        return response()->json($logs);
    }
}
