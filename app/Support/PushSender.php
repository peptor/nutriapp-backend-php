<?php

namespace App\Support;

use App\Mail\GenericPushMail;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

// Enviament de notificacions push (Web Push) a tots els dispositius d'un usuari. El missatge és sempre GENÈRIC
// (sense dades de salut): el pacient o el nutricionista ha d'entrar a NutriEvo per veure de què tracta.
// Si l'usuari no té les push autoritzades, no té cap dispositiu registrat, o l'enviament falla del tot (cap
// dispositiu el rep), s'envia un correu de fallback amb el mateix contingut — només si ho ha autoritzat
// (`notifyByEmailFallback`). Mai els dos alhora: el correu és exclusivament el pla B de la push.
// Vegeu docs/com-funcionen-les-alertes.md. Claus VAPID: `php artisan push:generate-keys`.
class PushSender
{
    private ?WebPush $webPush = null;

    // null si el servidor encara no té les claus VAPID configurades (`config/services.php`).
    private function client(): ?WebPush
    {
        if ($this->webPush) {
            return $this->webPush;
        }
        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');
        if (! $publicKey || ! $privateKey) {
            return null;
        }

        return $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);
    }

    /**
     * Envia l'avís per push; si no arriba a cap dispositiu, per correu (fallback, si l'usuari ho ha autoritzat).
     * Esborra en silenci els dispositius que el navegador ja no reconeix (410/404).
     */
    public function sendToUser(User $user, string $title, string $body, ?string $url = null, ?string $tag = null): void
    {
        if ($this->trySendPush($user, $title, $body, $url, $tag)) {
            return;
        }
        $this->sendFallbackEmail($user, $title, $body, $url);
    }

    // true si com a mínim un dispositiu l'ha rebuda (no cal el correu de fallback).
    private function trySendPush(User $user, string $title, string $body, ?string $url, ?string $tag): bool
    {
        if (! $user->notifyByPush) {
            return false;
        }
        $webPush = $this->client();
        if (! $webPush) {
            return false;
        }
        $subscriptions = PushSubscription::where('userId', $user->id)->get();
        if ($subscriptions->isEmpty()) {
            return false;
        }

        // Un enviament fallit (subscripció corrupta, servidor push inabastable...) mai ha de trencar el flux
        // principal (desar un registre, sincronitzar alertes): es registra i es tracta com a "no lliurat".
        try {
            $payload = json_encode(compact('title', 'body', 'url', 'tag'), JSON_UNESCAPED_UNICODE);
            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(
                    new Subscription($subscription->endpoint, $subscription->publicKey, $subscription->authToken, $subscription->contentEncoding),
                    $payload
                );
            }

            $delivered = false;
            $expiredEndpoints = [];
            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $delivered = true;
                } elseif ($report->isSubscriptionExpired()) {
                    $expiredEndpoints[] = $report->getEndpoint();
                } else {
                    report(new \RuntimeException('Web Push: '.$report->getReason().' ('.$report->getEndpoint().')'));
                }
            }
            if ($expiredEndpoints) {
                PushSubscription::where('userId', $user->id)->whereIn('endpoint', $expiredEndpoints)->delete();
            }

            return $delivered;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    private function sendFallbackEmail(User $user, string $title, string $body, ?string $url): void
    {
        if (! $user->notifyByEmailFallback || ! $user->email) {
            return;
        }
        try {
            Mail::to($user->email)->send(new GenericPushMail(
                $user->name,
                $title,
                $body,
                $url ? rtrim(config('app.frontend_url'), '/').$url : null,
                $user->language
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
