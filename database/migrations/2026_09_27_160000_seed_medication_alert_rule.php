<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Decisió 4 (docs/disseny-migracions-regles-camps.md): dos dies seguits sense prendre el tractament = a revisar.
// S'aplica als camps de medicació de la biblioteca i, com a la decisió 1, a les plantilles que en venen, però només
// si el camp encara no té cap alerta configurada (no es trepitja el que el nutricionista hagi editat).
return new class extends Migration
{
    private const NAMES = ['meds', 'medication'];

    private const RULE = ['alertValue' => false, 'alertLevel' => 'REVIEW', 'alertConsecutiveDays' => 2];

    public function up(): void
    {
        DB::table('mst_library_routine_fields')
            ->whereIn('name', self::NAMES)->where('fieldType', 'BOOLEAN')->where('alertLevel', 'NONE')
            ->update(self::RULE);

        $libraryIds = DB::table('mst_library_routine_fields')->whereIn('name', self::NAMES)->where('fieldType', 'BOOLEAN')->pluck('id');
        DB::table('mst_routine_fields')
            ->whereIn('sourceLibraryFieldId', $libraryIds)->where('alertLevel', 'NONE')->whereNull('alertValue')
            ->update(self::RULE);
    }

    public function down(): void
    {
        $reset = ['alertValue' => null, 'alertLevel' => 'NONE', 'alertConsecutiveDays' => null];
        $libraryIds = DB::table('mst_library_routine_fields')->whereIn('name', self::NAMES)->pluck('id');
        DB::table('mst_routine_fields')->whereIn('sourceLibraryFieldId', $libraryIds)->where('alertLevel', 'REVIEW')->where('alertConsecutiveDays', 2)->update($reset);
        DB::table('mst_library_routine_fields')->whereIn('name', self::NAMES)->where('alertLevel', 'REVIEW')->where('alertConsecutiveDays', 2)->update($reset);
    }
};
