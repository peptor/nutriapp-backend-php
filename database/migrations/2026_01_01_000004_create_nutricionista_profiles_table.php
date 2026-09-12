<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutricionista_profiles', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('userId', 36)->unique();
            $table->string('companyName')->nullable();
            $table->string('taxId')->nullable();
            $table->string('address')->nullable();
            $table->string('postalCode')->nullable();
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->string('logoUrl')->nullable();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('userId')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutricionista_profiles');
    }
};
