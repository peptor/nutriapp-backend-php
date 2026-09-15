<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('routine_fields', function (Blueprint $table) {
            $table->string('unit', 20)->nullable()->after('goodDirection');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routine_fields', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
