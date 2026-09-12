<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_routine_instructions', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('libraryRoutineId', 36);
            $table->string('title');
            $table->text('content');
            $table->integer('orderIndex')->default(0);

            $table->foreign('libraryRoutineId')->references('id')->on('library_routines')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_routine_instructions');
    }
};
