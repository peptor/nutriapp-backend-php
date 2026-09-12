<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_logs', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('userId', 36);
            $table->enum('userRole', ['ADMIN', 'NUTRICIONISTA', 'PACIENT']);
            $table->string('action');
            $table->string('targetType');
            $table->string('targetId', 36)->nullable();
            $table->timestamp('createdAt')->useCurrent();

            $table->foreign('userId')->references('id')->on('users');
            $table->index(['targetType', 'targetId']);
            $table->index('userId');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
    }
};
