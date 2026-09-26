<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Text d'ajuda opcional d'un camp de plantilla (la biblioteca ja el tenia). Es mostra al pacient
// sota el camp i el nutricionista el pot editar a l'editor de plantilles.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mst_routine_fields', function (Blueprint $table) {
            $table->text('helpText')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mst_routine_fields', function (Blueprint $table) {
            $table->dropColumn('helpText');
        });
    }
};
