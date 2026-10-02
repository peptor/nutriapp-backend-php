<?php

namespace App\Http\Controllers;

use App\Support\Billing;
use App\Support\Licenses;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    // Pla del nutricionista autenticat (EvoDemo / EvoPro, amb data de fi) i les seves últimes llicències.
    public function me(Request $request)
    {
        $licenses = $request->user()->licenses()->orderByDesc('startsAt')->orderByDesc('createdAt')->get();

        return response()->json([
            'plan' => Licenses::summary($licenses),
            'usage' => Licenses::usage($request->user()),
            'billing' => ['enabled' => Billing::configured(), 'hasCustomer' => Billing::hasCustomer($request->user())],
            'history' => $licenses->take(10)->map(fn ($l) => [
                'id' => $l->id,
                'startsAt' => $l->startsAt->toDateString(),
                'endsAt' => $l->endsAt?->toDateString(),
                'billingPeriod' => $l->billingPeriod,
                'source' => $l->source,
                'revoked' => $l->revokedAt !== null,
            ])->values(),
        ]);
    }
}
