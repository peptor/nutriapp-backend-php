<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Omple el sentit ("quin extrem és el millor") dels camps d'escala de la biblioteca i la unitat dels
// camps numèrics que no la diuen ja a l'etiqueta. Només afecta les plantilles noves creades des de la
// biblioteca; les existents tenen la seva pròpia còpia.
//
// goodDirection: LOW = com més baix millor (símptomes, dolor, estrès); HIGH = com més alt millor.
// Es deixen sense sentit (NULL) les escales descriptives on cap extrem és millor per se:
//   carb_portion (racions d'hidrats), perceived_effort (Borg CR-10) i hunger (gana percebuda).
return new class extends Migration
{
    private const DIRECTIONS = [
        'symptoms' => 'LOW',        // símptomes digestius (celiaquia)
        'heartburn' => 'LOW',       // cremor o acidesa (RGE)
        'straining' => 'LOW',       // esforç per evacuar
        'pain' => 'LOW',            // dolor (restrenyiment, SII)
        'bloating' => 'LOW',        // inflor (SII)
        'stress' => 'LOW',          // estrès (SII)
        'salt_choices' => 'HIGH',   // eleccions baixes en sodi (renal): més eleccions = millor
        'sodium_choices' => 'HIGH', // eleccions baixes en sodi (DASH)
        'tolerance' => 'HIGH',      // tolerància a l'àpat (bariàtrica)
    ];

    // [etiqueta actual, etiqueta nova (sense la unitat), unitat]. Només es posa unitat als camps
    // que no la porten ja a l'etiqueta ("Racions de...", "Hores de son" no la necessiten).
    private const UNITS = [
        ['Pes (kg)', 'Pes', 'kg'],
        ['Aigua (gots)', 'Aigua', 'gots'],
        ['Activitat física (minuts)', 'Activitat física', 'min'],
        ['Activitat moderada (minuts)', 'Activitat moderada', 'min'],
        ['Moviment (minuts)', 'Moviment', 'min'],
        ['Líquids ingerits (ml aproximats)', 'Líquids ingerits (aproximat)', 'ml'],
        ['Líquids (ml aproximats)', 'Líquids (aproximat)', 'ml'],
        ['Proteïna consumida (g aproximats)', 'Proteïna consumida (aproximat)', 'g'],
        ['Hidratació (litres aproximats)', 'Hidratació (aproximada)', 'L'],
        ['Glucosa abans (si està indicada)', 'Glucosa abans (si està indicada)', 'mg/dL'],
        ['Glucosa 2 h després (si està indicada)', 'Glucosa 2 h després (si està indicada)', 'mg/dL'],
    ];

    public function up(): void
    {
        foreach (self::DIRECTIONS as $name => $direction) {
            DB::table('mst_library_routine_fields')
                ->where('fieldType', 'SCALE')
                ->where('name', $name)
                ->update(['goodDirection' => $direction]);
        }

        foreach (self::UNITS as [$from, $to, $unit]) {
            DB::table('mst_library_routine_fields')
                ->where('fieldType', 'NUMBER')
                ->where('label', $from)
                ->update(['label' => $to, 'unit' => $unit]);
        }
    }

    public function down(): void
    {
        DB::table('mst_library_routine_fields')->update(['goodDirection' => null]);

        foreach (self::UNITS as [$from, $to, $unit]) {
            DB::table('mst_library_routine_fields')
                ->where('fieldType', 'NUMBER')
                ->where('label', $to)
                ->where('unit', $unit)
                ->update(['label' => $from, 'unit' => null]);
        }
    }
};
