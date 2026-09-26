<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// La taula de camps de la biblioteca no tenia goodDirection ni unit (sí que els té mst_routine_fields).
// Sense goodDirection, les plantilles creades des de la biblioteca no generaven alertes d'escala ni
// mostraven si l'evolució era favorable.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mst_library_routine_fields', function (Blueprint $table) {
            $table->enum('goodDirection', ['LOW', 'HIGH'])->nullable();
            $table->string('unit', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mst_library_routine_fields', function (Blueprint $table) {
            $table->dropColumn(['goodDirection', 'unit']);
        });
    }
};
