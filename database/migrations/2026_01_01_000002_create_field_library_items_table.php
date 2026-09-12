<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_library_items', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('createdById', 36)->nullable();
            $table->string('name');
            $table->string('label');
            $table->enum('fieldType', ['TEXT', 'NUMBER', 'SCALE', 'BOOLEAN', 'SELECT', 'MULTISELECT', 'DATETIME', 'PHOTO', 'MEAL', 'SYMPTOM', 'BLOOD_PRESSURE']);
            $table->string('frequency');
            $table->boolean('required')->default(true);
            $table->json('options')->nullable();
            $table->text('helpText')->nullable();
            $table->timestamp('createdAt')->useCurrent();

            $table->foreign('createdById')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_library_items');
    }
};
