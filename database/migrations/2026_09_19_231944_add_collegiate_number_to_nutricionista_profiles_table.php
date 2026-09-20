<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Número de col·legiat professional (p.ex. "CAT001234"): es mostra a la capçalera dels
// documents (informes, factures...) al costat del nom del nutricionista.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_nutricionista_profiles', function (Blueprint $table) {
            $table->string('collegiateNumber', 40)->nullable()->after('taxId');
        });
    }

    public function down(): void
    {
        Schema::table('sys_nutricionista_profiles', function (Blueprint $table) {
            $table->dropColumn('collegiateNumber');
        });
    }
};
