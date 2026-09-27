<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    // Els camps de defecació i evacuació passen de la icona "Estómac" a una de nova, "Intestins".
    // (El down() torna tots els camps de la biblioteca a "Estómac", també els que abans tenien una altra icona.)
    private const NAMES = ['bristol', 'bowel_movements', 'straining', 'liquid_stools', 'blood_stool', 'bowel_satisfaction', 'stool'];

    public function up(): void
    {
        DB::table('mst_fieldicons')->insert([
            'id' => (string) Str::uuid(),
            'key' => 'intestines',
            'label' => 'Intestins',
            'colorToken' => 'orange',
            'createdAt' => now(),
        ]);

        $intestinesId = DB::table('mst_fieldicons')->where('key', 'intestines')->value('id');

        foreach (['mst_library_routine_fields', 'mst_field_library_items'] as $table) {
            DB::table($table)->whereIn('name', self::NAMES)->update(['fieldIconId' => $intestinesId]);
        }

        // Les rutines ja creades només canvien si tenien l'estómac o cap icona: no es trepitja una triada a mà.
        $stomachId = DB::table('mst_fieldicons')->where('key', 'stomach')->value('id');
        DB::table('mst_routine_fields')->whereIn('name', self::NAMES)
            ->where(fn ($q) => $q->whereNull('fieldIconId')->orWhere('fieldIconId', $stomachId))
            ->update(['fieldIconId' => $intestinesId]);
    }

    public function down(): void
    {
        $stomachId = DB::table('mst_fieldicons')->where('key', 'stomach')->value('id');
        $intestinesId = DB::table('mst_fieldicons')->where('key', 'intestines')->value('id');

        if ($intestinesId) {
            foreach (['mst_library_routine_fields', 'mst_field_library_items', 'mst_routine_fields'] as $table) {
                DB::table($table)->where('fieldIconId', $intestinesId)->update(['fieldIconId' => $stomachId]);
            }
            DB::table('mst_fieldicons')->where('id', $intestinesId)->delete();
        }
    }
};
