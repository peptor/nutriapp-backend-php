<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_instructions', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('templateId', 36);
            $table->string('title');
            $table->text('content');
            $table->integer('orderIndex')->default(0);

            $table->foreign('templateId')->references('id')->on('routine_templates')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_instructions');
    }
};
