<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Aplica el rang que li correspon a cada camp d'escala de la biblioteca ara que l'escala és
// configurable (scaleMin/scaleMax). Només afecta les plantilles noves creades des de la
// biblioteca: les existents tenen la seva pròpia còpia i conserven el rang 0-10.
// - Gana percebuda (Patró saludable): escala de gana 1-10.
// L'esforç percebut de nutrició esportiva ja és Borg CR-10 (0-10): no canvia.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('mst_library_routine_fields')
            ->where('name', 'hunger')
            ->where('fieldType', 'SCALE')
            ->update(['scaleMin' => 1, 'scaleMax' => 10, 'label' => 'Gana percebuda (1-10)']);
    }

    public function down(): void
    {
        DB::table('mst_library_routine_fields')
            ->where('name', 'hunger')
            ->where('fieldType', 'SCALE')
            ->update(['scaleMin' => 0, 'scaleMax' => 10, 'label' => 'Gana percebuda (0-10)']);
    }
};
