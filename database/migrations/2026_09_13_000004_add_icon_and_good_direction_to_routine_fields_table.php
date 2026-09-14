<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_fields', function (Blueprint $table) {
            $table->string('fieldIconId', 36)->nullable()->after('fieldType');
            // Només té sentit per a camps de tipus SCALE: quin extrem (0 o 10) representa
            // el valor bo, per poder saber si una pujada o baixada és una evolució favorable.
            $table->enum('goodDirection', ['LOW', 'HIGH'])->nullable()->after('fieldIconId');

            $table->foreign('fieldIconId')->references('id')->on('mst_fieldicons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('routine_fields', function (Blueprint $table) {
            $table->dropForeign(['fieldIconId']);
            $table->dropColumn(['fieldIconId', 'goodDirection']);
        });
    }
};
