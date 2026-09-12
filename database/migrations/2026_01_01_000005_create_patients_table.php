<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('userId', 36);
            $table->string('nutricionistaId', 36);
            $table->date('birthDate')->nullable();
            $table->enum('gender', ['NO_DEFINIT', 'HOME', 'DONA'])->default('NO_DEFINIT');
            $table->string('photoUrl')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('userId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('nutricionistaId')->references('id')->on('users');
            $table->unique(['userId', 'nutricionistaId']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
