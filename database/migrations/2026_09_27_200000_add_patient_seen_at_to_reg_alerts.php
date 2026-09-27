<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// El pacient també veu les alertes (menú Avisos) i necessita el seu propi "vist": `seenAt` és del nutricionista.
// `patientSeenAt` és nul mentre el pacient no ha obert Avisos; alimenta el numeret del menú.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reg_alerts', function (Blueprint $table) {
            $table->timestamp('patientSeenAt')->nullable()->after('resolvedAt');
        });
    }

    public function down(): void
    {
        Schema::table('reg_alerts', function (Blueprint $table) {
            $table->dropColumn('patientSeenAt');
        });
    }
};
