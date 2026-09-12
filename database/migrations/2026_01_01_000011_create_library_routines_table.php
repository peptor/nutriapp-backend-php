<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_routines', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description');
            $table->text('objective')->nullable();
            $table->integer('durationDays');
            $table->text('caution')->nullable();
            $table->string('sourceName');
            $table->text('sourceUrl');
            $table->timestamp('createdAt')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_routines');
    }
};
