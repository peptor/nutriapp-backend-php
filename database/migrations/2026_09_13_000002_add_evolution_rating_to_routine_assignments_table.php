<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_assignments', function (Blueprint $table) {
            // El nutricionista la valora en el moment de completar la rutina: com ha anat
            // el pacient (POSITIVE/STABLE/NEGATIVE). Nul·la mentre la rutina no s'ha completat.
            $table->enum('evolutionRating', ['POSITIVE', 'STABLE', 'NEGATIVE'])->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('routine_assignments', function (Blueprint $table) {
            $table->dropColumn('evolutionRating');
        });
    }
};
