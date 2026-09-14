<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_templates', function (Blueprint $table) {
            $table->string('iconId', 36)->nullable()->after('objective');
            $table->foreign('iconId')->references('id')->on('mst_fieldicons')->nullOnDelete();
        });

        // Assignació manual de moment (sense selector a la UI encara), com amb els camps.
        $iconIdByKey = DB::table('mst_fieldicons')->pluck('id', 'key');
        $assignments = [
            'Personal Celiaquia: seguiment sense gluten' => 'leaf',
            'Prova pressió' => 'running',
            'Patró DASH i reducció de sodi' => 'heart',
            'Plantilla test rutina restrenyiment' => 'stomach',
            'Reflux gastroesofàgic (RGE)' => 'stomach',
            'Diabetis: mètode del plat' => 'apple',
            'Patró saludable i control de pes' => 'apple',
        ];
        foreach ($assignments as $name => $iconKey) {
            if (! isset($iconIdByKey[$iconKey])) {
                continue;
            }
            DB::table('routine_templates')->where('name', $name)->update(['iconId' => $iconIdByKey[$iconKey]]);
        }
    }

    public function down(): void
    {
        Schema::table('routine_templates', function (Blueprint $table) {
            $table->dropForeign(['iconId']);
            $table->dropColumn('iconId');
        });
    }
};
