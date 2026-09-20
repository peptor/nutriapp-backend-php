<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Patró d'horari del nutricionista: setmana única (WEEKLY) o dues setmanes que s'alternen
// (BIWEEKLY, Setmana A / Setmana B). cycleStartDate + cycleStartWeek són l'àncora que permet
// calcular, per a qualsevol data futura, si li toca la setmana A o la B.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_nutricionista_profiles', function (Blueprint $table) {
            $table->enum('scheduleMode', ['WEEKLY', 'BIWEEKLY'])->default('WEEKLY')->after('brandingTheme');
            $table->date('scheduleCycleStartDate')->nullable()->after('scheduleMode');
            $table->enum('scheduleCycleStartWeek', ['A', 'B'])->default('A')->after('scheduleCycleStartDate');
        });
    }

    public function down(): void
    {
        Schema::table('sys_nutricionista_profiles', function (Blueprint $table) {
            $table->dropColumn(['scheduleMode', 'scheduleCycleStartDate', 'scheduleCycleStartWeek']);
        });
    }
};
