<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Factures de NutriEvo als nutricionistes (una per pagament de quota EvoPro, vegeu App\Support\Invoices). Numeració
// correlativa per any (NE-AAAA-NNNN, sense forats) garantida per l'únic (year, sequence). Les dades de l'emissor i del
// destinatari es desen com a FOTO a l'emissió (issuer/recipient): una factura emesa no canvia encara que després el
// nutricionista modifiqui les seves dades. Imports en cèntims; la quota cobrada inclou l'IVA (base = total / (1+IVA)).
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reg_invoices')) {
            return;
        }

        Schema::create('reg_invoices', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('nutricionistaId', 36);
            $table->string('licenseId', 36)->unique();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence');
            $table->string('number', 20)->unique();
            $table->date('issuedAt');
            $table->date('periodStart');
            $table->date('periodEnd');
            $table->string('concept');
            $table->unsignedInteger('baseCents');
            $table->decimal('vatRate', 5, 2);
            $table->unsignedInteger('vatCents');
            $table->unsignedInteger('totalCents');
            $table->char('currency', 3)->default('EUR');
            $table->json('issuer');
            $table->json('recipient');
            $table->string('paymentRef')->nullable();
            $table->timestamp('emailedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();

            $table->foreign('nutricionistaId')->references('id')->on('sys_users')->cascadeOnDelete();
            $table->foreign('licenseId')->references('id')->on('reg_nutricionista_licenses')->cascadeOnDelete();
            $table->unique(['year', 'sequence'], 'reg_invoices_year_sequence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reg_invoices');
    }
};
