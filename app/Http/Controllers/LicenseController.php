<?php

namespace App\Http\Controllers;

use App\Support\Billing;
use App\Support\Licenses;
use App\Support\Paged;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    // Una llicència tal com la veu el nutricionista a l'historial del seu pla.
    private function present($l): array
    {
        return [
            'id' => $l->id,
            'startsAt' => $l->startsAt->toDateString(),
            'endsAt' => $l->endsAt?->toDateString(),
            'billingPeriod' => $l->billingPeriod,
            'source' => $l->source,
            'revoked' => $l->revokedAt !== null,
        ];
    }

    // Historial de llicències del nutricionista, PAGINAT al servidor (norma «Llistes llargues»): { data, page, perPage, total, hasMore }.
    public function history(Request $request)
    {
        $query = $request->user()->licenses()->orderByDesc('startsAt')->orderByDesc('createdAt')->orderBy('id');

        return response()->json(Paged::of($query, $request, fn ($l) => $this->present($l), 10));
    }

    // Pla del nutricionista autenticat (EvoDemo / EvoPro, amb data de fi) i la vista prèvia (5 últimes) de les seves llicències
    // amb el total a `historyCount`; la resta es demana a `history`.
    public function me(Request $request)
    {
        $licenses = $request->user()->licenses()->orderByDesc('startsAt')->orderByDesc('createdAt')->get();

        return response()->json([
            'plan' => Licenses::summary($licenses),
            'usage' => Licenses::usage($request->user()),
            'billing' => [
                'enabled' => Billing::configured(),
                'hasCustomer' => Billing::hasCustomer($request->user()),
                'fiscalDataComplete' => Billing::fiscalDataComplete($request->user()),
            ],
            'history' => $licenses->take(5)->map(fn ($l) => $this->present($l))->values(),
            'historyCount' => $licenses->count(),
        ]);
    }
}
