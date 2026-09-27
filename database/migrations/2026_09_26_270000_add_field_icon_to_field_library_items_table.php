<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Els camps de la biblioteca de camps també porten icona: la mateixa que té el camp
    // homònim a les rutines de la biblioteca, i per als camps propis més típics una de suggerida.
    public function up(): void
    {
        Schema::table('mst_field_library_items', function (Blueprint $table) {
            $table->string('fieldIconId', 36)->nullable()->after('fieldType');

            $table->foreign('fieldIconId')->references('id')->on('mst_fieldicons')->nullOnDelete();
        });

        $iconIdByKey = DB::table('mst_fieldicons')->pluck('id', 'key');
        $suggested = ['simptomes' => 'stomach', 'pressio_arterial' => 'heart'];

        foreach (DB::table('mst_field_library_items')->get(['id', 'name']) as $item) {
            $iconId = isset($suggested[$item->name])
                ? ($iconIdByKey[$suggested[$item->name]] ?? null)
                : DB::table('mst_library_routine_fields')->where('name', $item->name)->whereNotNull('fieldIconId')->value('fieldIconId');

            if ($iconId) {
                DB::table('mst_field_library_items')->where('id', $item->id)->update(['fieldIconId' => $iconId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('mst_field_library_items', function (Blueprint $table) {
            $table->dropForeign(['fieldIconId']);
            $table->dropColumn('fieldIconId');
        });
    }
};
