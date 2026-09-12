<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_routine_foods', function (Blueprint $table) {
            $table->string('libraryRoutineId', 36);
            $table->string('foodId', 36);
            $table->enum('use', ['RECOMMENDED', 'LIMIT', 'AVOID']);
            $table->text('note')->nullable();

            $table->primary(['libraryRoutineId', 'foodId']);
            $table->foreign('libraryRoutineId')->references('id')->on('library_routines')->onDelete('cascade');
            $table->foreign('foodId')->references('id')->on('foods');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_routine_foods');
    }
};
