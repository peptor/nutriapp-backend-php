<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SetPasswordRequest;
use App\Http\Requests\UpdateOwnProfileRequest;
use App\Mail\PasswordResetMail;
use App\Models\Patient;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Support\UrlHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private function publicUser(User $user): array
    {
        $avatarUrl = match ($user->role) {
            'NUTRICIONISTA' => $user->nutricionistaProfile?->logoUrl,
            'PACIENT' => Patient::where('userId', $user->id)->whereNotNull('photoUrl')->value('photoUrl'),
            default => null,
        };

        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
            'phone' => $user->phone,
            'avatarUrl' => UrlHelper::toAbsoluteUrl($avatarUrl),
            'brandingTheme' => $user->role === 'NUTRICIONISTA' ? ($user->nutricionistaProfile?->brandingTheme ?? 'classic') : 'classic',
        ];
    }

    private function issueToken(User $user): string
    {
        return $user->createToken('api')->plainTextToken;
    }

    // El registre públic no crea pacients ni administradors: un pacient necessita
    // un nutricionista responsable i els administradors els crea un altre administrador.
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        if (User::where('email', $data['email'])->exists()) {
            return response()->json(['error' => 'Email ja existeix'], 409);
        }

        $user = User::create([
            'email' => $data['email'],
            'passwordHash' => Hash::make($data['password']),
            'name' => $data['name'],
            'role' => 'NUTRICIONISTA',
            'phone' => $data['phone'] ?? null,
        ]);

        return response()->json([
            'user' => $this->publicUser($user),
            'token' => $this->issueToken($user),
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();

        if (! $user || $user->deletedAt) {
            return response()->json(['error' => 'Email o contrasenya incorrectes'], 401);
        }

        // Pacient creat pel nutricionista/admin sense contrasenya: encara no en té cap a
        // comprovar. En lloc de rebutjar el login, indiquem al frontend que mostri el
        // diàleg per establir-ne una (veure POST /set-password).
        if ($user->passwordHash === null) {
            return response()->json(['needsPasswordSetup' => true]);
        }

        if (! Hash::check($data['password'], $user->passwordHash)) {
            return response()->json(['error' => 'Email o contrasenya incorrectes'], 401);
        }

        return response()->json([
            'user' => $this->publicUser($user),
            'token' => $this->issueToken($user),
        ]);
    }

    // Primer login d'un pacient creat sense contrasenya: en lloc de demanar-ne l'antiga
    // (no n'hi ha), només cal la nova. Estableix-la i inicia sessió directament.
    public function setPassword(SetPasswordRequest $request)
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();

        if (! $user || $user->deletedAt) {
            return response()->json(['error' => 'Usuari no trobat'], 404);
        }
        if ($user->passwordHash !== null) {
            return response()->json(['error' => 'Aquest compte ja té una contrasenya. Utilitza el login normal.'], 409);
        }

        $user->update(['passwordHash' => Hash::make($data['newPassword'])]);

        return response()->json([
            'user' => $this->publicUser($user),
            'token' => $this->issueToken($user),
        ]);
    }

    private const PASSWORD_RESET_EXPIRES_MINUTES = 10;

    // Envia un enllaç de restabliment per email. Resposta genèrica sempre (encara que
    // l'email no existeixi), per no revelar quins comptes existeixen al sistema.
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->whereNull('deletedAt')->first();

        if ($user) {
            PasswordResetToken::where('userId', $user->id)->whereNull('usedAt')->delete();

            $rawToken = Str::random(64);
            PasswordResetToken::create([
                'userId' => $user->id,
                'tokenHash' => hash('sha256', $rawToken),
                'expiresAt' => now()->addMinutes(self::PASSWORD_RESET_EXPIRES_MINUTES),
            ]);

            $resetUrl = rtrim(config('app.frontend_url'), '/').'/reset-password?token='.$rawToken;

            Mail::to($user->email)->send(new PasswordResetMail($user->name, $resetUrl, self::PASSWORD_RESET_EXPIRES_MINUTES));
        }

        return response()->json(['message' => "Si existeix un compte amb aquest correu, t'hem enviat un enllaç per restablir la contrasenya."]);
    }

    // Estableix una contrasenya nova a partir d'un enllaç de restabliment vàlid (no caducat, no usat).
    public function resetPassword(ResetPasswordRequest $request)
    {
        $data = $request->validated();
        $tokenHash = hash('sha256', $data['token']);

        $resetToken = PasswordResetToken::where('tokenHash', $tokenHash)->whereNull('usedAt')->first();

        if (! $resetToken || $resetToken->expiresAt->isPast()) {
            return response()->json(['error' => "L'enllaç no és vàlid o ha caducat. Torna a demanar-ne un de nou."], 400);
        }

        $user = User::find($resetToken->userId);
        if (! $user || $user->deletedAt) {
            return response()->json(['error' => 'Usuari no trobat'], 404);
        }

        $user->update(['passwordHash' => Hash::make($data['newPassword'])]);
        $resetToken->update(['usedAt' => now()]);
        $user->tokens()->delete(); // revoca les sessions actives per seguretat

        return response()->json(['message' => 'Contrasenya actualitzada correctament']);
    }

    public function me(Request $request)
    {
        return response()->json($this->publicUser($request->user()));
    }

    // Actualitza les dades pròpies (mai el rol, que només pot canviar un administrador)
    public function updateMe(UpdateOwnProfileRequest $request)
    {
        $data = $request->validated();
        $current = $request->user();

        if (isset($data['email']) && $data['email'] !== $current->email && User::where('email', $data['email'])->exists()) {
            return response()->json(['error' => 'Email ja en ús'], 409);
        }

        $current->update(array_intersect_key($data, array_flip(['name', 'email', 'phone'])));

        return response()->json($this->publicUser($current->fresh()));
    }

    // Canvia la contrasenya pròpia: cal la contrasenya actual i la nova ha de complir la política mínima
    public function changePassword(ChangePasswordRequest $request)
    {
        $data = $request->validated();
        $current = $request->user();

        if (! $current->passwordHash || ! Hash::check($data['currentPassword'], $current->passwordHash)) {
            // 400, no 401: un 401 dispararia el logout automàtic global de l'interceptor
            // d'axios (veure frontend/src/lib/api.ts), tot i que l'usuari segueix autenticat.
            return response()->json(['error' => 'La contrasenya actual no és correcta'], 400);
        }

        $current->update(['passwordHash' => Hash::make($data['newPassword'])]);

        return response()->json(['message' => 'Contrasenya actualitzada correctament']);
    }

    public function exportMe(Request $request)
    {
        $user = User::with([
            'patientProfiles.assignments.records',
            'patientProfiles.assignments.template.fields',
            'patientProfiles.nutricionista:id,name',
            'createdRoutines.fields',
        ])->find($request->user()->id);

        if (! $user) {
            return response()->json(['error' => 'Usuari no trobat'], 404);
        }

        return response()->json([
            ...$this->publicUser($user),
            'patientProfiles' => $user->patientProfiles,
            'createdRoutines' => $user->createdRoutines,
        ]);
    }

    public function deleteMe(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'ADMIN' && User::where('role', 'ADMIN')->whereNull('deletedAt')->where('id', '!=', $user->id)->count() === 0) {
            return response()->json(['error' => "No es pot eliminar l'últim administrador"], 409);
        }
        if ($user->role === 'NUTRICIONISTA' && Patient::where('nutricionistaId', $user->id)->count() > 0) {
            return response()->json(['error' => "Reassigna els pacients abans d'eliminar el compte"], 409);
        }

        $user->update([
            'name' => 'Usuari eliminat',
            'email' => "deleted-{$user->id}@anonymized.local",
            'phone' => null,
            'passwordHash' => 'DISABLED',
            'deletedAt' => now(),
        ]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Compte eliminat correctament']);
    }
}
