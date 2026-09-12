<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('patientId', 36);
            $table->string('assignmentId', 36)->nullable();
            $table->string('type');
            $table->text('message');
            $table->string('severity')->default('info');
            $table->boolean('isRead')->default(false);
            $table->timestamp('createdAt')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
