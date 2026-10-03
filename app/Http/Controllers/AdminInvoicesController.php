<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\Paged;
use Illuminate\Http\Request;

// Factures de NutriEvo vistes per l'administrador (CRM): totes les de tots els nutricionistes, filtrables per dates
// d'emissió i per nutricionista. La llista és PAGINADA al servidor (norma «Llistes llargues») i els totals (base, IVA i total)
// es calculen amb SQL sobre TOT el filtre, no sobre la pàgina; l'exportació a CSV (per al gestor) la genera el servidor sobre tot
// el filtre. El detall d'una factura és `GET /invoices/{id}` (InvoicesController::show), que també deixa passar els administradors.
class AdminInvoicesController extends Controller
{
    private function filtered(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'nutricionistaId' => ['nullable', 'string', 'max:36'],
        ]);

        return Invoice::with('nutricionista:id,name,email')
            ->when($data['from'] ?? null, fn ($q, $from) => $q->where('issuedAt', '>=', $from))
            ->when($data['to'] ?? null, fn ($q, $to) => $q->where('issuedAt', '<=', $to))
            ->when($data['nutricionistaId'] ?? null, fn ($q, $id) => $q->where('nutricionistaId', $id))
            ->orderByDesc('issuedAt')->orderByDesc('year')->orderByDesc('sequence')->orderBy('id');
    }

    // ?from=YYYY-MM-DD&to=YYYY-MM-DD (data d'emissió, ambdues incloses) &nutricionistaId=... &page&perPage
    // → { data, page, perPage, total, hasMore, totals: { count, baseCents, vatCents, totalCents } } (totals de tot el filtre).
    public function index(Request $request)
    {
        $query = $this->filtered($request);
        $sums = (clone $query)->reorder()->toBase()->selectRaw('count(*) as n, coalesce(sum(baseCents), 0) as base, coalesce(sum(vatCents), 0) as vat, coalesce(sum(totalCents), 0) as total')->first();

        return response()->json(Paged::of($query, $request, fn (Invoice $i) => [
            'id' => $i->id,
            'number' => $i->number,
            'issuedAt' => $i->issuedAt->toDateString(),
            'concept' => $i->concept,
            'baseCents' => $i->baseCents,
            'vatRate' => $i->vatRate,
            'vatCents' => $i->vatCents,
            'totalCents' => $i->totalCents,
            'currency' => $i->currency,
            'nutricionistaId' => $i->nutricionistaId,
            'nutricionistaName' => $i->nutricionista?->name,
            'clientName' => $i->recipient['name'] ?? null,
            'clientTaxId' => $i->recipient['taxId'] ?? null,
            'emailed' => $i->emailedAt !== null,
        ], 25) + [
            'totals' => [
                'count' => (int) $sums->n,
                'baseCents' => (int) $sums->base,
                'vatCents' => (int) $sums->vat,
                'totalCents' => (int) $sums->total,
            ],
        ]);
    }

    // CSV (per al gestor) de TOT el filtre, no només de la pàgina carregada: es genera per blocs, sense carregar-lo sencer.
    // Imports en euros amb coma decimal i separador «;», perquè s'obri bé amb Excel en català/castellà.
    public function export(Request $request)
    {
        $query = $this->filtered($request);
        $euros = fn (int $cents) => number_format($cents / 100, 2, ',', '');
        $cell = fn ($value) => '"'.str_replace('"', '""', (string) ($value ?? '')).'"';
        $name = 'factures-'.($request->query('from') ?: 'inici').'_'.($request->query('to') ?: 'avui').'.csv';

        return response()->streamDownload(function () use ($query, $euros, $cell) {
            echo "\xEF\xBB\xBF".implode(';', array_map($cell, ['Número', 'Data', 'Nutricionista', 'Client', 'NIF/CIF', 'Concepte', 'Base imposable', 'IVA %', 'IVA', 'Total', 'Moneda']))."\r\n";
            $query->chunk(500, function ($invoices) use ($euros, $cell) {
                foreach ($invoices as $i) {
                    echo implode(';', array_map($cell, [
                        $i->number, $i->issuedAt->format('d/m/Y'), $i->nutricionista?->name, $i->recipient['name'] ?? null, $i->recipient['taxId'] ?? null,
                        $i->concept, $euros($i->baseCents), str_replace('.', ',', (string) $i->vatRate), $euros($i->vatCents), $euros($i->totalCents), $i->currency,
                    ]))."\r\n";
                }
            });
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
