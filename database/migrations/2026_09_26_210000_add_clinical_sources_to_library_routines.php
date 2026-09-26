<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Guies clíniques o instruments de referència de cada rutina de la biblioteca, com a llista de
// {name, url}. Es guarden a banda de sourceName/sourceUrl, que són la pàgina per al pacient
// (NIDDK, AHA, ADA...): aquelles expliquen el tema al pacient, aquestes permeten al professional
// comprovar de quina guia depèn cada rutina.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mst_library_routines', function (Blueprint $table) {
            $table->json('clinicalSources')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mst_library_routines', function (Blueprint $table) {
            $table->dropColumn('clinicalSources');
        });
    }
};
