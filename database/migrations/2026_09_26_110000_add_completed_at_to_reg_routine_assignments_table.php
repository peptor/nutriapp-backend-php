<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reg_routine_assignments', function (Blueprint $table) {
            $table->timestamp('completedAt')->nullable()->after('evolutionRating');
        });

        // Les rutines ja completades no guardaven quan: la millor aproximació és l'últim
        // canvi de la fila. Fixem updatedAt a si mateix perquè aquest UPDATE no el modifiqui.
        DB::statement("UPDATE reg_routine_assignments SET completedAt = updatedAt, updatedAt = updatedAt WHERE status = 'COMPLETED'");
    }

    public function down(): void
    {
        Schema::table('reg_routine_assignments', function (Blueprint $table) {
            $table->dropColumn('completedAt');
        });
    }
};
