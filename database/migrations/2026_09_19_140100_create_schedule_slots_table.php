<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Trams horaris del patró habitual: un dia (dayOfWeek 0=dilluns..6=diumenge) pot tenir
// diversos trams (matí + tarda). `week` és NULL en mode WEEKLY (un sol patró) o 'A'/'B' en
// mode BIWEEKLY (dues setmanes que s'alternen, veure sys_nutricionista_profiles).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_schedule_slots', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('nutricionistaId', 36);
            $table->enum('week', ['A', 'B'])->nullable();
            $table->unsignedTinyInteger('dayOfWeek');
            $table->time('startTime');
            $table->time('endTime');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('nutricionistaId')->references('id')->on('sys_users')->onDelete('cascade');
            $table->index(['nutricionistaId', 'week', 'dayOfWeek']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_schedule_slots');
    }
};
