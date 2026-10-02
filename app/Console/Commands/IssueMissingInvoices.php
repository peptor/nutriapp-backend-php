<?php

namespace App\Console\Commands;

use App\Models\NutricionistaLicense;
use App\Support\Invoices;
use Illuminate\Console\Command;

// Emet les factures dels pagaments de quota que encara no en tenen (p. ex. perquè quan es va cobrar encara no estaven
// configurades les dades de l'emissor INVOICE_ISSUER_*) i envia per correu les que no han sortit. Idempotent.
class IssueMissingInvoices extends Command
{
    protected $signature = 'invoices:issue-missing';

    protected $description = 'Emet les factures pendents dels pagaments de quota EvoPro';

    public function handle(): int
    {
        if (! Invoices::issuerConfigured()) {
            $this->error('Falten les dades de l\'emissor (INVOICE_ISSUER_NAME i INVOICE_ISSUER_TAX_ID al .env).');

            return self::FAILURE;
        }

        $done = 0;
        NutricionistaLicense::where('source', 'PAYMENT')->where('amountCents', '>', 0)->orderBy('createdAt')->each(function (NutricionistaLicense $license) use (&$done) {
            if (Invoices::ensureForLicense($license)) {
                $done++;
            }
        });
        $this->info("Factures comprovades/emeses: $done");

        return self::SUCCESS;
    }
}
