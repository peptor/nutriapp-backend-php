<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Afegeix nom_en a mst_aliments i mst_categories, que ciqual:translate-names --lang=en
     * espera trobar-hi. Als aliments s'omple ja amb el nom anglès oficial de Ciqual
     * (nom_eng), molt millor que una traducció automàtica del francès; les categories no en
     * tenen i les omplirà la comanda de traducció.
     */
    public function up(): void
    {
        Schema::table('mst_aliments', function (Blueprint $table) {
            $table->string('nom_en', 500)->nullable()->after('nom_it');
        });

        Schema::table('mst_categories', function (Blueprint $table) {
            $table->string('nom_en')->nullable()->after('nom_it');
        });

        DB::table('mst_aliments')
            ->whereNotNull('nom_eng')
            ->where('nom_eng', '<>', '')
            ->update(['nom_en' => DB::raw('nom_eng')]);
    }

    public function down(): void
    {
        Schema::table('mst_aliments', function (Blueprint $table) {
            $table->dropColumn('nom_en');
        });

        Schema::table('mst_categories', function (Blueprint $table) {
            $table->dropColumn('nom_en');
        });
    }
};
