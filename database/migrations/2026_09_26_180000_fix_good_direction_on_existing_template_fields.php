<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Corregeix el sentit ("quin extrem és el millor") dels camps d'escala de les plantilles que ja
// existeixen i venen de la biblioteca. L'antic reompliment (2026_09_19_201949) va posar HIGH a tot
// el que estava en blanc, així que símptomes com el dolor o l'ardor es llegien com "com més alt
// millor" i les alertes de l'informe d'evolució sortien al revés.
//
// Només es toquen camps que coincideixen amb un de la biblioteca en nom i etiqueta, i només si tenen
// el valor per defecte (buit o HIGH): un LOW triat pel nutricionista no es modifica mai.
// Els sentits són els mateixos que 2026_09_26_171000 (veure'l per al criteri).
//
// down(): no hi ha manera de saber quin era el valor original de cada fila (HIGH o buit), així que
// no es desfà, com l'antic reompliment.
return new class extends Migration
{
    // [nom, etiqueta] => sentit
    private const LOW = [
        ['symptoms', 'Símptomes digestius (0-10)'],
        ['heartburn', 'Cremor o acidesa (0-10)'],
        ['straining', 'Esforç per evacuar (0-10)'],
        ['pain', 'Dolor o inflor (0-10)'],
        ['pain', 'Dolor abdominal (0-10)'],
        ['bloating', 'Inflor (0-10)'],
        ['stress', 'Estrès (0-10)'],
    ];

    private const HIGH = [
        ['salt_choices', 'Eleccions baixes en sodi'],
        ['sodium_choices', 'Eleccions baixes en sodi'],
        ['tolerance', "Tolerància a l'àpat (0-10)"],
    ];

    // Escales descriptives on cap extrem és millor per se: es queden sense sentit.
    private const NEUTRAL = [
        ['carb_portion', "Ració d'hidrats (0-10)"],
        ['perceived_effort', 'Esforç percebut (0-10)'],
        ['hunger', 'Gana percebuda (0-10)'],
    ];

    private function scaleFields(string $name, string $label)
    {
        return DB::table('mst_routine_fields')
            ->where('fieldType', 'SCALE')
            ->where('name', $name)
            ->where('label', $label);
    }

    public function up(): void
    {
        foreach (self::LOW as [$name, $label]) {
            $this->scaleFields($name, $label)
                ->where(fn ($q) => $q->whereNull('goodDirection')->orWhere('goodDirection', 'HIGH'))
                ->update(['goodDirection' => 'LOW']);
        }

        foreach (self::HIGH as [$name, $label]) {
            $this->scaleFields($name, $label)->whereNull('goodDirection')->update(['goodDirection' => 'HIGH']);
        }

        foreach (self::NEUTRAL as [$name, $label]) {
            $this->scaleFields($name, $label)->where('goodDirection', 'HIGH')->update(['goodDirection' => null]);
        }
    }

    public function down(): void
    {
        // Correcció de dades irreversible: veure el comentari de dalt.
    }
};
