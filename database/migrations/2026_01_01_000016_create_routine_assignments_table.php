<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_assignments', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('patientId', 36);
            $table->string('templateId', 36);
            $table->date('startDate');
            $table->date('endDate');
            $table->enum('status', ['DRAFT', 'ACTIVE', 'COMPLETED', 'CANCELLED'])->default('ACTIVE');
            $table->text('customNotes')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('patientId')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('templateId')->references('id')->on('routine_templates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_assignments');
    }
};
