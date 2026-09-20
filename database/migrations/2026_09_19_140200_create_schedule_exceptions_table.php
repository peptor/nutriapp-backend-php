<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Dies puntuals que trenquen el patró habitual: festius, absències i vacances (no treballa,
// sense horari) o un horari especial (amb startTime/endTime propis, diferents del patró).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reg_schedule_exceptions', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('nutricionistaId', 36);
            $table->date('date');
            $table->enum('type', ['FESTIU', 'ABSENCIA', 'VACANCES', 'HORARI_ESPECIAL']);
            $table->time('startTime')->nullable();
            $table->time('endTime')->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('nutricionistaId')->references('id')->on('sys_users')->onDelete('cascade');
            $table->unique(['nutricionistaId', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reg_schedule_exceptions');
    }
};
