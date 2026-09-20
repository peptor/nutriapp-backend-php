<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Els camps d'escala 0-10 creats abans de tenir el selector "quin extrem és el millor"
    // (o que simplement no el van triar) es queden sense goodDirection. Per defecte els
    // marquem com a HIGH (el 10 és el millor), que és el cas més habitual (adherència,
    // satisfacció, energia...).
    public function up(): void
    {
        DB::table('mst_routine_fields')
            ->where('fieldType', 'SCALE')
            ->whereNull('goodDirection')
            ->update(['goodDirection' => 'HIGH']);
    }

    public function down(): void
    {
        // Backfill de dades: no hi ha manera de saber quins registres eren originalment
        // NULL, així que no es desfà.
    }
};
