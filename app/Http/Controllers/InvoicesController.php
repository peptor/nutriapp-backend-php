<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\Invoices;
use App\Support\Paged;
use Illuminate\Http\Request;

class InvoicesController extends Controller
{
    // Factures del nutricionista autenticat, de la més recent a la més antiga. PAGINADES al servidor (norma «Llistes llargues»):
    // { data, page, perPage, total, hasMore } amb ?page&perPage.
    public function index(Request $request)
    {
        $query = Invoice::where('nutricionistaId', $request->user()->id)
            ->orderByDesc('year')->orderByDesc('sequence')->orderBy('id');

        return response()->json(Paged::of($query, $request, fn (Invoice $i) => [
            'id' => $i->id,
            'number' => $i->number,
            'issuedAt' => $i->issuedAt->toDateString(),
            'concept' => $i->concept,
            'totalCents' => $i->totalCents,
            'currency' => $i->currency,
        ], 10));
    }

    // Una factura completa (només la pròpia).
    public function show(Request $request, string $id)
    {
        $invoice = Invoice::when($request->user()->role !== 'ADMIN', fn ($q) => $q->where('nutricionistaId', $request->user()->id))->find($id);
        if (! $invoice) {
            return response()->json(['error' => 'Factura no trobada'], 404);
        }

        return response()->json(Invoices::present($invoice));
    }
}
