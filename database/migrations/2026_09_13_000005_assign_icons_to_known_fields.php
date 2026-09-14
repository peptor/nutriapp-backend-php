<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Assignació manual (de moment no hi ha selector d'icones a la UI): els camps ja
    // existents que apareixen a les captures de disseny reben la icona i, si són d'escala
    // 0-10, l'extrem "bo" corresponent.
    public function up(): void
    {
        $iconIdByKey = DB::table('mst_fieldicons')->pluck('id', 'key');

        $assignments = [
            ['label' => 'Àpats 100% sense gluten', 'iconKey' => 'utensils'],
            ['label' => 'Símptomes digestius (0-10)', 'iconKey' => 'stomach', 'goodDirection' => 'LOW'],
            ['label' => 'Possible contacte creuat amb gluten', 'iconKey' => 'warning'],
            ['label' => "Revisió d'etiquetes o certificació sense gluten", 'iconKey' => 'document'],
            ['label' => 'Notes sobre àpats fora de casa o dubtes', 'iconKey' => 'document'],
        ];

        foreach ($assignments as $a) {
            if (! isset($iconIdByKey[$a['iconKey']])) {
                continue;
            }
            DB::table('routine_fields')->where('label', $a['label'])->update([
                'fieldIconId' => $iconIdByKey[$a['iconKey']],
                'goodDirection' => $a['goodDirection'] ?? null,
            ]);
        }
    }

    public function down(): void
    {
        // No es desfà: assignar una icona no és una operació destructiva a revertir.
    }
};
