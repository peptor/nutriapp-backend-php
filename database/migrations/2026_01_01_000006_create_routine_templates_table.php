<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_templates', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('durationDays');
            $table->text('objective')->nullable();
            $table->boolean('isPublic')->default(false);
            $table->string('createdById', 36)->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->boolean('foodLogEnabled')->default(false);

            $table->foreign('createdById')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_templates');
    }
};
