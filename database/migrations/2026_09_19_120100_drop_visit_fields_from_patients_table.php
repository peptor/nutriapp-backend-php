<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 'Última visita' i 'Pròxima cita' ara es calculen a partir de reg_appointments
// (la cita passada més recent / la futura més propera) en lloc de guardar-se com a
// camps manuals independents al perfil del pacient.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_patients', function (Blueprint $table) {
            $table->dropColumn(['lastVisitAt', 'nextAppointmentAt']);
        });
    }

    public function down(): void
    {
        Schema::table('sys_patients', function (Blueprint $table) {
            $table->dateTime('lastVisitAt')->nullable()->after('notes');
            $table->dateTime('nextAppointmentAt')->nullable()->after('lastVisitAt');
        });
    }
};
