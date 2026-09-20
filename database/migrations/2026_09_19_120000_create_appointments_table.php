<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reg_appointments', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('patientId', 36);
            $table->string('nutricionistaId', 36);
            $table->dateTime('startAt');
            $table->dateTime('endAt');
            $table->string('reason', 500)->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('patientId')->references('id')->on('sys_patients')->onDelete('cascade');
            $table->foreign('nutricionistaId')->references('id')->on('sys_users')->onDelete('cascade');
            $table->index(['nutricionistaId', 'startAt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reg_appointments');
    }
};
