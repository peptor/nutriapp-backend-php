<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Registre dels recordatoris de registre ja enviats (App\Support\RegisterReminders): abans no es desaven enlloc
// (només push en calent). Ara queden guardats per poder mostrar-ne el compte setmanal a l'Avui del pacient
// (targeta de la rutina) i el detall (App\Support\RegisterReminders, Send{Morning,Evening}Reminders).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reg_reminder_notices', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('assignmentId', 36);
            $table->enum('period', ['MORNING', 'EVENING']);
            $table->date('recordDate');
            $table->timestamp('createdAt')->useCurrent();

            $table->foreign('assignmentId')->references('id')->on('reg_routine_assignments')->cascadeOnDelete();
            $table->unique(['assignmentId', 'period', 'recordDate'], 'reg_reminder_notices_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reg_reminder_notices');
    }
};
