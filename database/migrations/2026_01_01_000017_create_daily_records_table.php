<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_records', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('assignmentId', 36);
            $table->string('patientId', 36);
            $table->date('recordDate');
            $table->string('fieldName');
            $table->json('value');
            $table->text('notes')->nullable();
            $table->enum('recordedBy', ['PACIENT', 'NUTRICIONISTA'])->default('PACIENT');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('assignmentId')->references('id')->on('routine_assignments')->onDelete('cascade');
            $table->foreign('patientId')->references('id')->on('patients')->onDelete('cascade');
            $table->unique(['assignmentId', 'recordDate', 'fieldName']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_records');
    }
};
