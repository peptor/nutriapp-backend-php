<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Escala configurable: fins ara el tipus SCALE era sempre 0-10. Cada camp passa a tenir el seu
// rang (scaleMin/scaleMax, per defecte 0 i 10, així que els camps existents no canvien).
// Només s'aplica als camps de tipus SCALE; per als altres tipus els valors s'ignoren.
return new class extends Migration
{
    private const TABLES = ['mst_routine_fields', 'mst_library_routine_fields', 'mst_field_library_items'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedSmallInteger('scaleMin')->default(0);
                $t->unsignedSmallInteger('scaleMax')->default(10);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['scaleMin', 'scaleMax']);
            });
        }
    }
};
