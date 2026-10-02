<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cobrament amb Stripe Billing: el client de Stripe del nutricionista (per obrir-li el portal de subscripció i
// relacionar cada factura pagada amb el seu compte) i un índex únic sobre `paymentRef` (l'id de la factura de
// Stripe) perquè el webhook sigui idempotent: una mateixa factura mai no crea dues llicències.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_nutricionista_profiles', function (Blueprint $table) {
            $table->string('stripeCustomerId')->nullable()->unique();
        });
        Schema::table('reg_nutricionista_licenses', function (Blueprint $table) {
            $table->unique('paymentRef', 'reg_nutri_licenses_payment_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reg_nutricionista_licenses', function (Blueprint $table) {
            $table->dropUnique('reg_nutri_licenses_payment_ref_unique');
        });
        Schema::table('sys_nutricionista_profiles', function (Blueprint $table) {
            $table->dropUnique(['stripeCustomerId']);
            $table->dropColumn('stripeCustomerId');
        });
    }
};
