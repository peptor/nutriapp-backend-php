<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_patients', function (Blueprint $table) {
            $table->dateTime('lastVisitAt')->nullable()->after('notes');
            $table->dateTime('nextAppointmentAt')->nullable()->after('lastVisitAt');
        });
    }

    public function down(): void
    {
        Schema::table('sys_patients', function (Blueprint $table) {
            $table->dropColumn(['lastVisitAt', 'nextAppointmentAt']);
        });
    }
};
