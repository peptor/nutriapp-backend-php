<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_fields', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('templateId', 36);
            $table->string('name');
            $table->string('label');
            $table->enum('fieldType', ['TEXT', 'NUMBER', 'SCALE', 'BOOLEAN', 'SELECT', 'MULTISELECT', 'DATETIME', 'PHOTO', 'MEAL', 'SYMPTOM', 'BLOOD_PRESSURE']);
            $table->string('frequency');
            $table->boolean('required')->default(true);
            $table->json('options')->nullable();
            $table->integer('orderIndex')->default(0);
            $table->timestamp('createdAt')->useCurrent();

            $table->foreign('templateId')->references('id')->on('routine_templates')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_fields');
    }
};
