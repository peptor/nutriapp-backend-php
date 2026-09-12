<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_template_foods', function (Blueprint $table) {
            $table->string('templateId', 36);
            $table->string('foodId', 36);
            $table->enum('use', ['RECOMMENDED', 'LIMIT', 'AVOID']);
            $table->text('note')->nullable();

            $table->primary(['templateId', 'foodId']);
            $table->foreign('templateId')->references('id')->on('routine_templates')->onDelete('cascade');
            $table->foreign('foodId')->references('id')->on('foods');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_template_foods');
    }
};
