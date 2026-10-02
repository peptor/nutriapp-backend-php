<?php

namespace App\Support;

use App\Models\NutricionistaLicense;
use App\Models\NutricionistaProfile;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

// Cobrament dels nutricionistes amb Stripe Billing (subscripció mensual o anual). Es parla amb l'API REST d'Stripe
// directament (sense SDK). Flux:
//  1. El nutricionista prem "Passar a EvoPro" -> checkoutUrl() crea una sessió de Stripe Checkout (mode subscripció):
//     paga i Stripe en desa la targeta (o el mandat SEPA) per cobrar-lo cada mes/any automàticament.
//  2. Cada factura pagada (la primera i les renovacions) arriba al webhook (BillingController::webhook) com a
//     `invoice.paid` i handleInvoicePaid() crea una llicència EvoPro fins al final del període pagat.
//  3. Per canviar la targeta, veure factures o donar-se de baixa: portalUrl() (portal de client d'Stripe).
// Si no paga o es dona de baixa, no cal fer res: la llicència caduca a la data pagada i torna sol a EvoDemo.
// Configuració (.env): STRIPE_SECRET, STRIPE_WEBHOOK_SECRET, STRIPE_PRICE_MONTHLY, STRIPE_PRICE_ANNUAL.
class Billing
{
    private const API = 'https://api.stripe.com/v1';

    // Marge (segons) acceptat entre el timestamp de la signatura del webhook i ara.
    private const SIGNATURE_TOLERANCE = 300;

    public static function configured(): bool
    {
        return (bool) config('services.stripe.secret')
            && (bool) config('services.stripe.webhook_secret')
            && (bool) config('services.stripe.price_monthly')
            && (bool) config('services.stripe.price_annual');
    }

    private static function http(): PendingRequest
    {
        return Http::withBasicAuth((string) config('services.stripe.secret'), '')->asForm()->acceptJson()->timeout(20);
    }

    private static function call(string $path, array $params): array
    {
        $response = self::http()->post(self::API.$path, $params);
        if ($response->failed()) {
            throw new RuntimeException('Stripe: '.($response->json('error.message') ?? "error {$response->status()}"));
        }

        return $response->json();
    }

    // Client de Stripe del nutricionista: es crea la primera vegada i es desa al seu perfil.
    private static function customerId(User $nutricionista): string
    {
        $profile = NutricionistaProfile::firstOrCreate(['userId' => $nutricionista->id]);
        if ($profile->stripeCustomerId) {
            return $profile->stripeCustomerId;
        }
        $customer = self::call('/customers', [
            'email' => $nutricionista->email,
            'name' => $nutricionista->name,
            'metadata' => ['userId' => $nutricionista->id],
        ]);
        $profile->forceFill(['stripeCustomerId' => $customer['id']])->save();

        return $customer['id'];
    }

    // La factura necessita la raó social i el NIF/CIF del client: sense ells no es pot pagar la llicència.
    public static function fiscalDataComplete(User $nutricionista): bool
    {
        $profile = NutricionistaProfile::where('userId', $nutricionista->id)->first(['companyName', 'taxId']);

        return $profile && trim((string) $profile->companyName) !== '' && trim((string) $profile->taxId) !== '';
    }

    public static function hasCustomer(User $nutricionista): bool
    {
        return (bool) NutricionistaProfile::where('userId', $nutricionista->id)->value('stripeCustomerId');
    }

    private static function frontendUrl(string $query): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/settings?tab=pla&'.$query;
    }

    // URL de Stripe Checkout per subscriure's. $period: MONTHLY | ANNUAL.
    public static function checkoutUrl(User $nutricionista, string $period): string
    {
        $price = $period === 'ANNUAL' ? config('services.stripe.price_annual') : config('services.stripe.price_monthly');
        $session = self::call('/checkout/sessions', [
            'mode' => 'subscription',
            'customer' => self::customerId($nutricionista),
            'client_reference_id' => $nutricionista->id,
            'line_items' => [['price' => $price, 'quantity' => 1]],
            'subscription_data' => ['metadata' => ['userId' => $nutricionista->id]],
            'locale' => 'auto',
            'success_url' => self::frontendUrl('checkout=ok'),
            'cancel_url' => self::frontendUrl('checkout=cancel'),
        ]);

        return $session['url'];
    }

    // URL del portal de client d'Stripe (canviar targeta, factures, baixa).
    public static function portalUrl(User $nutricionista): string
    {
        $session = self::call('/billing_portal/sessions', [
            'customer' => self::customerId($nutricionista),
            'return_url' => self::frontendUrl('portal=back'),
        ]);

        return $session['url'];
    }

    // Verifica la capçalera Stripe-Signature (HMAC-SHA256 de "timestamp.cos") i retorna l'esdeveniment, o null si no és vàlid.
    public static function verifiedEvent(string $payload, ?string $header): ?array
    {
        if (! $header) {
            return null;
        }
        $parts = [];
        foreach (explode(',', $header) as $item) {
            [$k, $v] = array_pad(explode('=', $item, 2), 2, null);
            $parts[$k][] = $v;
        }
        $timestamp = (int) ($parts['t'][0] ?? 0);
        if (! $timestamp || abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE) {
            return null;
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, (string) config('services.stripe.webhook_secret'));
        foreach ($parts['v1'] ?? [] as $signature) {
            if (hash_equals($expected, (string) $signature)) {
                return json_decode($payload, true);
            }
        }

        return null;
    }

    // `invoice.paid`: crea la llicència EvoPro del període pagat. Retorna la llicència, o null si no s'ha fet res
    // (factura ja processada o nutricionista desconegut).
    public static function handleInvoicePaid(array $invoice): ?NutricionistaLicense
    {
        $invoiceId = $invoice['id'] ?? null;
        $line = $invoice['lines']['data'][0] ?? null;
        if (! $invoiceId || ! $line || empty($line['period']['end'])) {
            return null;
        }
        $existing = NutricionistaLicense::where('paymentRef', $invoiceId)->first();
        if ($existing) {
            // Idempotència: Stripe pot reenviar el mateix esdeveniment. Si la factura no va arribar a emetre's (p. ex.
            // faltaven les dades de l'emissor), aquí es recupera.
            self::issueInvoice($existing);

            return null;
        }

        $nutricionistaId = NutricionistaProfile::where('stripeCustomerId', $invoice['customer'] ?? '')->value('userId')
            ?? ($line['metadata']['userId'] ?? null);
        $nutricionista = $nutricionistaId ? User::where('role', 'NUTRICIONISTA')->whereNull('deletedAt')->find($nutricionistaId) : null;
        if (! $nutricionista) {
            return null;
        }

        $start = Carbon::createFromTimestampUTC($line['period']['start'] ?? $invoice['created'] ?? time());
        $end = Carbon::createFromTimestampUTC($line['period']['end']);

        $license = NutricionistaLicense::create([
            'nutricionistaId' => $nutricionista->id,
            'startsAt' => $start->toDateString(),
            'endsAt' => $end->toDateString(),
            // Mensual o anual segons la durada del període pagat (independent de la versió de l'API d'Stripe).
            'billingPeriod' => $start->diffInDays($end) > 60 ? 'ANNUAL' : 'MONTHLY',
            'source' => 'PAYMENT',
            'amountCents' => (int) ($invoice['amount_paid'] ?? 0),
            'currency' => strtoupper((string) ($invoice['currency'] ?? 'eur')),
            'paymentRef' => $invoiceId,
        ]);
        self::issueInvoice($license);

        return $license;
    }

    // La factura de NutriEvo del pagament. Un error aquí mai no ha de tombar el webhook (la llicència ja està creada):
    // es registra i `php artisan invoices:issue-missing` ho pot recuperar.
    private static function issueInvoice(NutricionistaLicense $license): void
    {
        try {
            Invoices::ensureForLicense($license);
        } catch (\Throwable $e) {
            Log::error('Factura del pagament '.$license->paymentRef.': '.$e->getMessage());
        }
    }
}
