<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\NutricionistaProfile;
use App\Models\User;
use App\Support\Licenses;
use Illuminate\Support\Carbon;

// Resum de negoci de l'Inici de l'administrador (CRM): facturació dels últims mesos, nutricionistes EvoPro / EvoDemo,
// llicències que caduquen aviat (amb l'estat del recordatori) i les que han caducat fa poc.
class AdminOverviewController extends Controller
{
    public const EXPIRING_DAYS = 30;

    public function index()
    {
        $today = Carbon::today();

        // Facturació per mes d'emissió (6 mesos, comptant l'actual), de l'antic al recent.
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = $today->copy()->startOfMonth()->subMonths($i);
        }
        $invoices = Invoice::where('issuedAt', '>=', $months[0]->toDateString())->get(['issuedAt', 'baseCents', 'vatCents', 'totalCents']);
        $billing = collect($months)->map(function (Carbon $m) use ($invoices) {
            $inMonth = $invoices->filter(fn ($i) => $i->issuedAt->format('Y-m') === $m->format('Y-m'));

            return [
                'month' => $m->format('Y-m'),
                'count' => $inMonth->count(),
                'baseCents' => (int) $inMonth->sum('baseCents'),
                'vatCents' => (int) $inMonth->sum('vatCents'),
                'totalCents' => (int) $inMonth->sum('totalCents'),
            ];
        })->values();

        // Plans i caducitats.
        $nutris = User::where('role', 'NUTRICIONISTA')->whereNull('deletedAt')->with('licenses')->orderBy('name')->get();
        $stripe = NutricionistaProfile::whereNotNull('stripeCustomerId')->pluck('userId')->flip();
        $pro = 0;
        $expiring = [];
        $expired = [];
        foreach ($nutris as $n) {
            $plan = Licenses::summary($n->licenses);
            if ($plan['code'] === Licenses::PRO) {
                $pro++;
                if (! $plan['indefinite'] && $plan['until']) {
                    $days = (int) $today->diffInDays(Carbon::parse($plan['until']), false);
                    if ($days <= self::EXPIRING_DAYS) {
                        $last = $n->licenses->first(fn ($l) => $l->endsAt?->toDateString() === $plan['until']);
                        $expiring[] = [
                            'nutricionistaId' => $n->id,
                            'name' => $n->name,
                            'email' => $n->email,
                            'until' => $plan['until'],
                            'daysLeft' => $days,
                            'reminderSent' => (bool) $last?->expiryReminderSentAt,
                            'autoRenew' => $stripe->has($n->id) && $last?->source === 'PAYMENT',
                        ];
                    }
                }
            } else {
                // EvoDemo que fins fa poc era EvoPro: han "caigut" (possible baixa) i val la pena contactar-los.
                $lastEnd = $n->licenses->filter(fn ($l) => $l->revokedAt === null && $l->endsAt)->max(fn ($l) => $l->endsAt->toDateString());
                if ($lastEnd && Carbon::parse($lastEnd)->gte($today->copy()->subDays(self::EXPIRING_DAYS))) {
                    $expired[] = ['nutricionistaId' => $n->id, 'name' => $n->name, 'email' => $n->email, 'endedAt' => $lastEnd];
                }
            }
        }
        usort($expiring, fn ($a, $b) => $a['daysLeft'] <=> $b['daysLeft']);

        return response()->json([
            'billing' => $billing,
            'plans' => ['pro' => $pro, 'demo' => $nutris->count() - $pro, 'total' => $nutris->count()],
            'expiring' => $expiring,
            'expired' => $expired,
        ]);
    }
}
