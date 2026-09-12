<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_routine_fields', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('libraryRoutineId', 36);
            $table->string('name');
            $table->string('label');
            $table->enum('fieldType', ['TEXT', 'NUMBER', 'SCALE', 'BOOLEAN', 'SELECT', 'MULTISELECT', 'DATETIME', 'PHOTO', 'MEAL', 'SYMPTOM', 'BLOOD_PRESSURE']);
            $table->string('frequency');
            $table->boolean('required')->default(true);
            $table->json('options')->nullable();
            $table->integer('orderIndex')->default(0);
            $table->text('helpText')->nullable();

            $table->foreign('libraryRoutineId')->references('id')->on('library_routines')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_routine_fields');
    }
};
