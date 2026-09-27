<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;

// Registre de dispositius per a notificacions push (Web Push). Només es desa la subscripció; l'enviament és una
// peça posterior (cal una llibreria Web Push i claus VAPID). Vegeu docs/com-funcionen-les-alertes.md.
class PushController extends Controller
{
    // Clau pública VAPID per subscriure's des del navegador. `enabled = false` si el servidor no té les claus configurades.
    public function config()
    {
        $publicKey = config('services.webpush.public_key');

        return response()->json(['enabled' => (bool) $publicKey && (bool) config('services.webpush.private_key'), 'publicKey' => $publicKey ?: null]);
    }

    // Dispositius de l'usuari. `endpointHash` (sha256 de l'endpoint) permet al navegador reconèixer el seu.
    public function index(Request $request)
    {
        return response()->json(
            PushSubscription::where('userId', $request->user()->id)->orderBy('createdAt')->get(['id', 'endpointHash', 'deviceLabel', 'lastUsedAt', 'createdAt'])
        );
    }

    // Registra (o actualitza) aquest dispositiu i autoritza les notificacions push de l'usuari.
    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['sometimes', 'string', 'max:20'],
        ]);
        $user = $request->user();
        $userAgent = mb_substr((string) $request->userAgent(), 0, 255);

        $subscription = PushSubscription::updateOrCreate(
            ['endpointHash' => hash('sha256', $data['endpoint'])],
            [
                'userId' => $user->id, // si el dispositiu canvia d'usuari, passa a l'actual
                'endpoint' => $data['endpoint'],
                'publicKey' => $data['keys']['p256dh'],
                'authToken' => $data['keys']['auth'],
                'contentEncoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'deviceLabel' => self::deviceLabel($userAgent),
                'userAgent' => $userAgent,
                'lastUsedAt' => now(),
            ]
        );
        if (! $user->notifyByPush) {
            $user->update(['notifyByPush' => true]);
        }

        return response()->json(['id' => $subscription->id, 'endpointHash' => $subscription->endpointHash, 'deviceLabel' => $subscription->deviceLabel], 201);
    }

    // Elimina un dispositiu de l'usuari (no en canvia l'autorització: per desactivar-les, vegeu updateNotifications).
    public function destroy(Request $request, string $id)
    {
        $deleted = PushSubscription::where('userId', $request->user()->id)->where('id', $id)->delete();

        return $deleted ? response()->json(['message' => 'Eliminat']) : response()->json(['error' => 'Dispositiu no trobat'], 404);
    }

    // Etiqueta llegible del dispositiu ("Chrome · Windows") a partir del User-Agent.
    private static function deviceLabel(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') || str_contains($userAgent, 'CriOS/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Navegador',
        };
        $system = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') || str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'dispositiu desconegut',
        };

        return $browser.' · '.$system;
    }
}
