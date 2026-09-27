<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Els camps favorits d'un nutricionista es desen a la biblioteca de camps: han de conservar
    // el sentit de l'escala i la unitat, com la resta de camps de rutina.
    public function up(): void
    {
        Schema::table('mst_field_library_items', function (Blueprint $table) {
            $table->enum('goodDirection', ['LOW', 'HIGH'])->nullable()->after('scaleMax');
            $table->string('unit', 20)->nullable()->after('goodDirection');
        });
    }

    public function down(): void
    {
        Schema::table('mst_field_library_items', function (Blueprint $table) {
            $table->dropColumn(['goodDirection', 'unit']);
        });
    }
};
