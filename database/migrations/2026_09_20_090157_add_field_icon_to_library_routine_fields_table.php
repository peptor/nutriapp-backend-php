<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mst_library_routine_fields', function (Blueprint $table) {
            $table->string('fieldIconId', 36)->nullable()->after('fieldType');

            $table->foreign('fieldIconId')->references('id')->on('mst_fieldicons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mst_library_routine_fields', function (Blueprint $table) {
            $table->dropForeign(['fieldIconId']);
            $table->dropColumn('fieldIconId');
        });
    }
};
