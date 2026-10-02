<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Llicències dels nutricionistes (monetització). El pla NO es guarda enlloc: es deriva d'aquest historial.
// Un nutricionista és "EvoPro" si té alguna llicència vigent (no revocada, ja començada i no caducada) i "EvoDemo"
// si no (vegeu App\Support\Licenses). Cada pagament/concessió és una fila nova (historial, no s'esborra mai):
// un pagament mensual allarga la llicència un mes més, un d'anual un any. `endsAt` NULL = sense caducitat.
//
// Aquesta migració NO crea cap llicència: en acabar, tots els nutricionistes existents queden a EvoDemo (decisió
// 02/10/2026). Per passar-ne un a EvoPro, l'administrador li concedeix una llicència fins a una data.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reg_nutricionista_licenses')) {
            return;
        }

        Schema::create('reg_nutricionista_licenses', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('nutricionistaId', 36);
            $table->date('startsAt');
            $table->date('endsAt')->nullable();
            $table->enum('billingPeriod', ['MONTHLY', 'ANNUAL', 'MANUAL'])->default('MANUAL');
            $table->enum('source', ['ADMIN', 'PAYMENT']);
            $table->unsignedInteger('amountCents')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('paymentRef')->nullable();
            $table->string('createdById', 36)->nullable();
            $table->string('note')->nullable();
            $table->timestamp('revokedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();

            $table->foreign('nutricionistaId')->references('id')->on('sys_users')->cascadeOnDelete();
            $table->foreign('createdById')->references('id')->on('sys_users')->nullOnDelete();
            $table->index(['nutricionistaId', 'endsAt'], 'reg_nutri_licenses_nutri_ends_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reg_nutricionista_licenses');
    }
};
