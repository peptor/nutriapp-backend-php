<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Els camps de la biblioteca que diuen "(si està indicada)" passen a ser opcionals: un pacient
// sense pauta de glucosa no pot ser obligat a registrar-la per poder desar el dia. La pressió
// arterial (DASH i renal) ja es va fer opcional a 2026_09_26_130000. Només afecta les plantilles
// noves creades des de la biblioteca.
return new class extends Migration
{
    private const FIELDS = ['glucose_before', 'glucose_after'];

    public function up(): void
    {
        DB::table('mst_library_routine_fields')
            ->whereIn('name', self::FIELDS)
            ->where('label', 'like', '%si està indicada%')
            ->update(['required' => 0]);
    }

    public function down(): void
    {
        DB::table('mst_library_routine_fields')
            ->whereIn('name', self::FIELDS)
            ->where('label', 'like', '%si està indicada%')
            ->update(['required' => 1]);
    }
};
