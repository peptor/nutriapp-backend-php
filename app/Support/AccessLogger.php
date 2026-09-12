<?php

namespace App\Support;

use App\Models\AccessLog;
use Throwable;

// Registre d'auditoria (RGPD): qui ha accedit a quines dades clíniques i quan.
// Es desa en segon pla (best-effort) perquè mai bloquegi ni faci fallar la resposta
// a l'usuari per un problema d'auditoria.
class AccessLogger
{
    public static function log(string $userId, string $userRole, string $action, string $targetType, ?string $targetId = null): void
    {
        try {
            AccessLog::create([
                'userId' => $userId,
                'userRole' => $userRole,
                'action' => $action,
                'targetType' => $targetType,
                'targetId' => $targetId,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
