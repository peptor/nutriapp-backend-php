<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\Invoices;
use Illuminate\Http\Request;

class InvoicesController extends Controller
{
    // Factures del nutricionista autenticat, de la més recent a la més antiga.
    public function index(Request $request)
    {
        $invoices = Invoice::where('nutricionistaId', $request->user()->id)
            ->orderByDesc('year')->orderByDesc('sequence')
            ->get(['id', 'number', 'issuedAt', 'concept', 'totalCents', 'currency']);

        return response()->json($invoices->map(fn (Invoice $i) => [
            'id' => $i->id,
            'number' => $i->number,
            'issuedAt' => $i->issuedAt->toDateString(),
            'concept' => $i->concept,
            'totalCents' => $i->totalCents,
            'currency' => $i->currency,
        ])->values());
    }

    // Una factura completa (només la pròpia).
    public function show(Request $request, string $id)
    {
        $invoice = Invoice::where('nutricionistaId', $request->user()->id)->find($id);
        if (! $invoice) {
            return response()->json(['error' => 'Factura no trobada'], 404);
        }

        return response()->json(Invoices::present($invoice));
    }
}
