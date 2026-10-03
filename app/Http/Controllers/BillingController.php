<?php

namespace App\Http\Controllers;

use App\Support\Billing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class BillingController extends Controller
{
    private function notConfigured()
    {
        return response()->json(['error' => 'El cobrament encara no està disponible. Contacta amb nosaltres.'], 503);
    }

    // Inicia el pagament d'EvoPro (Stripe Checkout): { period: MONTHLY | ANNUAL } -> { url } on cal redirigir el nutricionista.
    public function checkout(Request $request)
    {
        $period = $request->validate(['period' => ['required', 'in:MONTHLY,ANNUAL']])['period'];
        if (! Billing::configured()) {
            return $this->notConfigured();
        }
        if (! Billing::fiscalDataComplete($request->user())) {
            return response()->json([
                'error' => 'Per pagar la llicència cal indicar la raó social i el CIF/NIF a Configuració › Dades (secció «Dades de l\'empresa»): surten a la factura.',
                'code' => 'FISCAL_DATA_MISSING',
            ], 422);
        }
        try {
            return response()->json(['url' => Billing::checkoutUrl($request->user(), $period)]);
        } catch (Throwable $e) {
            Log::error('Stripe checkout: '.$e->getMessage());

            return response()->json(['error' => "No s'ha pogut iniciar el pagament. Torna-ho a provar més tard."], 502);
        }
    }

    // Portal de client d'Stripe: { url }.
    public function portal(Request $request)
    {
        if (! Billing::configured()) {
            return $this->notConfigured();
        }
        if (! Billing::hasCustomer($request->user())) {
            return response()->json(['error' => 'Encara no tens cap subscripció'], 409);
        }
        try {
            return response()->json(['url' => Billing::portalUrl($request->user())]);
        } catch (Throwable $e) {
            Log::error('Stripe portal: '.$e->getMessage());

            return response()->json(['error' => "No s'ha pogut obrir la gestió de la subscripció."], 502);
        }
    }

    // Webhook d'Stripe (sense sessió: es valida amb la signatura). Només ens interessa `invoice.paid`.
    public function webhook(Request $request)
    {
        $event = Billing::verifiedEvent($request->getContent(), $request->header('Stripe-Signature'));
        if (! $event) {
            return response()->json(['error' => 'Signatura no vàlida'], 400);
        }
        if (($event['type'] ?? null) === 'invoice.paid') {
            Billing::handleInvoicePaid($event['data']['object'] ?? []);
        }

        return response()->json(['received' => true]);
    }
}
