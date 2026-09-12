<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foods', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('imageUrl')->nullable();
            $table->string('categoryId', 36);

            $table->foreign('categoryId')->references('id')->on('food_categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foods');
    }
};
