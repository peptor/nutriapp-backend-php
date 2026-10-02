<?php

namespace App\Support;

use App\Mail\InvoiceIssuedMail;
use App\Models\Invoice;
use App\Models\NutricionistaLicense;
use App\Models\NutricionistaProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

// Factures de NutriEvo als nutricionistes per cada pagament de quota EvoPro (llicència source=PAYMENT):
// - Una factura per llicència (únic licenseId): emetre-la dues vegades no en crea una segona.
// - Numeració correlativa per any `NE-AAAA-NNNN`, sense forats (únic year+sequence, dins d'una transacció).
// - L'import cobrat (`amountCents`) INCLOU l'IVA: base = total / (1 + IVA), IVA = total - base. Una línia de factura:
//   la quota mensual/anual amb el seu període.
// - Emissor (NutriEvo, config/invoicing.php) i destinatari (perfil del nutricionista) es desen com a foto.
// - Si l'emissor no està configurat, no s'emet (es registra un avís) i `invoices:issue-missing` ho recupera després.
// - Després d'emetre-la s'envia un correu al nutricionista amb l'enllaç; si falla, no s'avorta res (es reintenta amb
//   `invoices:issue-missing`, que també envia les que no han sortit per correu).
// No és assessorament fiscal: el format s'ha de validar amb el gestor (VeriFactu, IVA intracomunitari, etc.).
class Invoices
{
    public static function issuerConfigured(): bool
    {
        return (bool) config('invoicing.issuer.name') && (bool) config('invoicing.issuer.taxId');
    }

    // Base i IVA (en cèntims) d'un total que inclou l'IVA.
    public static function split(int $totalCents, float $vatRate): array
    {
        $base = (int) round($totalCents / (1 + $vatRate / 100));

        return ['base' => $base, 'vat' => $totalCents - $base];
    }

    // Emet (si cal) la factura d'una llicència de pagament i la renvia per correu si encara no ha sortit.
    public static function ensureForLicense(NutricionistaLicense $license): ?Invoice
    {
        if ($license->source !== 'PAYMENT' || (int) $license->amountCents <= 0) {
            return null; // sense import cobrat no hi ha res a facturar
        }
        $invoice = Invoice::where('licenseId', $license->id)->first();
        if (! $invoice) {
            if (! self::issuerConfigured()) {
                Log::warning('Factura no emesa: falten les dades de l\'emissor (INVOICE_ISSUER_*).', ['licenseId' => $license->id]);

                return null;
            }
            $invoice = self::issue($license);
        }
        if (! $invoice->emailedAt) {
            self::email($invoice);
        }

        return $invoice;
    }

    private static function issue(NutricionistaLicense $license): Invoice
    {
        $nutricionista = User::findOrFail($license->nutricionistaId);
        $profile = NutricionistaProfile::where('userId', $nutricionista->id)->first();
        $vatRate = (float) config('invoicing.vat_rate');
        ['base' => $base, 'vat' => $vat] = self::split((int) $license->amountCents, $vatRate);
        $year = (int) now()->format('Y');
        $kind = $license->billingPeriod === 'ANNUAL' ? 'anual' : 'mensual';

        // Reintent curt: dues factures simultànies podrien triar el mateix número (el garanteix l'únic de la BD).
        return DB::transaction(function () use ($license, $nutricionista, $profile, $vatRate, $base, $vat, $year, $kind) {
            $sequence = (int) DB::table('reg_invoices')->where('year', $year)->lockForUpdate()->max('sequence') + 1;

            return Invoice::create([
                'nutricionistaId' => $nutricionista->id,
                'licenseId' => $license->id,
                'year' => $year,
                'sequence' => $sequence,
                'number' => sprintf('NE-%d-%04d', $year, $sequence),
                'issuedAt' => now()->toDateString(),
                'periodStart' => $license->startsAt->toDateString(),
                'periodEnd' => $license->endsAt->toDateString(),
                'concept' => sprintf('Quota %s EvoPro (%s – %s)', $kind, $license->startsAt->format('d/m/Y'), $license->endsAt->format('d/m/Y')),
                'baseCents' => $base,
                'vatRate' => $vatRate,
                'vatCents' => $vat,
                'totalCents' => (int) $license->amountCents,
                'currency' => $license->currency ?: 'EUR',
                'issuer' => config('invoicing.issuer'),
                'recipient' => [
                    'name' => $profile?->companyName ?: $nutricionista->name,
                    'contactName' => $nutricionista->name,
                    'taxId' => $profile?->taxId,
                    'address' => $profile?->address,
                    'postalCode' => $profile?->postalCode,
                    'city' => $profile?->city,
                    'email' => $nutricionista->email,
                ],
                'paymentRef' => $license->paymentRef,
            ]);
        }, 3);
    }

    private static function email(Invoice $invoice): void
    {
        try {
            $user = User::find($invoice->nutricionistaId);
            if (! $user) {
                return;
            }
            Mail::to($user->email)->send(new InvoiceIssuedMail(
                $user->name,
                $invoice->number,
                $invoice->concept,
                number_format($invoice->totalCents / 100, 2, ',', '.').' '.($invoice->currency === 'EUR' ? '€' : $invoice->currency),
                rtrim((string) config('app.frontend_url'), '/').'/invoices/'.$invoice->id,
                $user->language,
            ));
            $invoice->forceFill(['emailedAt' => now()])->save();
        } catch (Throwable $e) {
            Log::error('Correu de factura '.$invoice->number.': '.$e->getMessage());
        }
    }

    // Per a l'API: factura amb els imports ja en euros (decimals) i les dades de la foto.
    public static function present(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'issuedAt' => $invoice->issuedAt->toDateString(),
            'periodStart' => $invoice->periodStart->toDateString(),
            'periodEnd' => $invoice->periodEnd->toDateString(),
            'concept' => $invoice->concept,
            'baseCents' => $invoice->baseCents,
            'vatRate' => $invoice->vatRate,
            'vatCents' => $invoice->vatCents,
            'totalCents' => $invoice->totalCents,
            'currency' => $invoice->currency,
            'issuer' => $invoice->issuer,
            'recipient' => $invoice->recipient,
        ];
    }
}
