<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 5a (docs/disseny-migracions-regles-camps.md): alertes que veu el nutricionista. Es generen a partir de les
// incidències (App\Support\AlertGenerator) en desar registres, i tenen estat (obert, vist, resolt).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reg_alerts', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('assignmentId', 36);
            $table->string('fieldName', 60);
            $table->string('recordId', 36)->nullable();
            $table->date('recordDate');
            $table->enum('type', ['OUT_OF_RANGE', 'ALARM', 'TREND_WORSE', 'MISSING_DAYS', 'NOTE_KEYWORD'])->default('OUT_OF_RANGE');
            $table->enum('level', ['REVIEW', 'URGENT'])->default('REVIEW');
            $table->enum('severity', ['HIGH', 'LOW'])->default('HIGH');
            $table->string('value', 120)->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('message', 300);
            $table->enum('status', ['OPEN', 'SEEN', 'RESOLVED'])->default('OPEN');
            $table->timestamp('seenAt')->nullable();
            $table->timestamp('resolvedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();

            $table->foreign('assignmentId')->references('id')->on('reg_routine_assignments')->cascadeOnDelete();
            $table->foreign('recordId')->references('id')->on('reg_daily_records')->nullOnDelete();
            $table->unique(['assignmentId', 'fieldName', 'recordDate', 'type'], 'reg_alerts_unique');
            $table->index(['assignmentId', 'status']);
            $table->index(['status', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reg_alerts');
    }
};
