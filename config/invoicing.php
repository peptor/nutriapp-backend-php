<?php

// Facturació de NutriEvo als nutricionistes (vegeu App\Support\Invoices i docs/plans-i-llicencies.md).
// Les dades de l'EMISSOR (NutriEvo) han d'estar completes al .env: sense nom fiscal i NIF no s'emet cap factura
// (queda pendent; `php artisan invoices:issue-missing` les emet quan ja estigui configurat).
return [
    // IVA aplicat a la quota (%). La quota cobrada a Stripe ja l'inclou.
    'vat_rate' => (float) env('INVOICE_VAT_RATE', 21),

    'issuer' => [
        'name' => env('INVOICE_ISSUER_NAME'),
        'taxId' => env('INVOICE_ISSUER_TAX_ID'),
        'address' => env('INVOICE_ISSUER_ADDRESS'),
        'postalCode' => env('INVOICE_ISSUER_POSTAL_CODE'),
        'city' => env('INVOICE_ISSUER_CITY'),
        'country' => env('INVOICE_ISSUER_COUNTRY', 'Espanya'),
        'email' => env('INVOICE_ISSUER_EMAIL'),
    ],
];
