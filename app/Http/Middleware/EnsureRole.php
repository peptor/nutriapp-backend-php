<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Equivalent de requireRole(...) del backend Node: comprova que l'usuari autenticat
// tingui un dels rols indicats (ADMIN, NUTRICIONISTA, PACIENT).
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, $roles, true)) {
            return response()->json(['error' => 'Accés denegat'], 403);
        }

        return $next($request);
    }
}
