<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_routines', function (Blueprint $table) {
            $table->string('iconId', 36)->nullable()->after('name');
            $table->foreign('iconId')->references('id')->on('mst_fieldicons')->nullOnDelete();
        });

        // Icona il·lustrada per a cada rutina de la biblioteca clínica, més la icona
        // "Nutricionista" que és la que porten per defecte les rutines creades des de
        // zero (no venen de la biblioteca).
        $now = now();
        $icons = [
            'lib-patro-dash' => ['label' => 'Patró DASH', 'imageUrl' => '/routine-icons/dash.png'],
            'lib-patro-saludable' => ['label' => 'Patró saludable', 'imageUrl' => '/routine-icons/patro-saludable.png'],
            'lib-nutricionista' => ['label' => 'Nutricionista', 'imageUrl' => '/routine-icons/nutricionista.png'],
            'lib-cardiovascular' => ['label' => 'Control de colesterol', 'imageUrl' => '/routine-icons/cardiovascular.png'],
            'lib-sii-fodmap' => ['label' => 'SII / FODMAP', 'imageUrl' => '/routine-icons/sii-fodmap.png'],
            'lib-renal-cronica' => ['label' => 'Malaltia renal crònica', 'imageUrl' => '/routine-icons/renal-cronica.png'],
            'lib-bariatrica' => ['label' => 'Cirurgia bariàtrica', 'imageUrl' => '/routine-icons/bariatrica.png'],
            'lib-diabetis' => ['label' => 'Diabetis', 'imageUrl' => '/routine-icons/diabetis.png'],
            'lib-esportiva' => ['label' => 'Nutrició esportiva', 'imageUrl' => '/routine-icons/esportiva.png'],
            'lib-restrenyiment' => ['label' => 'Restrenyiment', 'imageUrl' => '/routine-icons/restrenyiment.png'],
            'lib-reflux' => ['label' => 'Reflux gastroesofàgic', 'imageUrl' => '/routine-icons/reflux.png'],
            'lib-celiaquia' => ['label' => 'Celiaquia', 'imageUrl' => '/routine-icons/celiaquia.png'],
        ];
        foreach ($icons as $key => $data) {
            DB::table('mst_fieldicons')->insert([
                'id' => (string) Str::uuid(),
                'key' => $key,
                'label' => $data['label'],
                'colorToken' => 'emerald',
                'imageUrl' => $data['imageUrl'],
                'createdAt' => $now,
            ]);
        }

        $iconIdByKey = DB::table('mst_fieldicons')->pluck('id', 'key');
        $nutricionistaIconId = $iconIdByKey['lib-nutricionista'];

        $libraryAssignments = [
            'Patró DASH i reducció de sodi' => 'lib-patro-dash',
            'Patró saludable i control de pes' => 'lib-patro-saludable',
            'Control de colesterol i salut cardiovascular' => 'lib-cardiovascular',
            'SII: seguiment FODMAP supervisat' => 'lib-sii-fodmap',
            'Malaltia renal crònica (sense diàlisi)' => 'lib-renal-cronica',
            'Postoperatori de cirurgia bariàtrica' => 'lib-bariatrica',
            'Diabetis: mètode del plat' => 'lib-diabetis',
            'Nutrició esportiva i rendiment' => 'lib-esportiva',
            'Rutina per restrenyiment' => 'lib-restrenyiment',
            'Reflux gastroesofàgic (RGE)' => 'lib-reflux',
            'Celiaquia: seguiment sense gluten' => 'lib-celiaquia',
        ];
        $libraryNames = [];
        foreach ($libraryAssignments as $name => $iconKey) {
            if (! isset($iconIdByKey[$iconKey])) {
                continue;
            }
            DB::table('library_routines')->where('name', $name)->update(['iconId' => $iconIdByKey[$iconKey]]);
            $libraryNames[] = $name;
        }

        // Plantilles ja existents: si el nom coincideix exactament amb una rutina de la
        // biblioteca, hereten la seva icona il·lustrada; la resta (creades/personalitzades
        // a mà) reben la icona genèrica "Nutricionista".
        DB::table('routine_templates')->whereIn('name', $libraryNames)->update(['iconId' => null]);
        foreach ($libraryAssignments as $name => $iconKey) {
            DB::table('routine_templates')->where('name', $name)->update(['iconId' => $iconIdByKey[$iconKey]]);
        }
        DB::table('routine_templates')->whereNotIn('name', $libraryNames)->update(['iconId' => $nutricionistaIconId]);
    }

    public function down(): void
    {
        Schema::table('library_routines', function (Blueprint $table) {
            $table->dropForeign(['iconId']);
            $table->dropColumn('iconId');
        });
    }
};
