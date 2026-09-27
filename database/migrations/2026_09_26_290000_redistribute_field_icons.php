<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Les 8 icones dels camps passen a ser: Cor, Hidratació, Alimentació, Símptomes i senyals,
    // Registre clínic, Digestiu, Alimentació saludable i Activitat física. Es reanomenen les files
    // existents (mateix id) perquè totes les rutines i camps que les feien servir les conservin,
    // i s'elimina la icona "Intestins", que ja no hi és: els seus camps tornen a les icones de sempre.
    private const RENAMES = [
        'leaf' => ['hydration', 'Hidratació', 'sky'],
        'utensils' => ['food', 'Alimentació', 'emerald'],
        'warning' => ['symptoms', 'Símptomes i senyals', 'amber'],
        'document' => ['clinical-record', 'Registre clínic', 'violet'],
        'stomach' => ['digestive', 'Digestiu', 'teal'],
        'apple' => ['healthy-eating', 'Alimentació saludable', 'emerald'],
        'running' => ['activity', 'Activitat física', 'orange'],
    ];

    // Camps que la icona "Intestins" s'havia endut i on tornen (per defecte, Digestiu).
    private const INTESTINES_TARGET = ['blood_stool' => 'symptoms', 'bowel_satisfaction' => 'heart'];

    private const TABLES = ['mst_library_routine_fields', 'mst_field_library_items', 'mst_routine_fields'];

    public function up(): void
    {
        $intestinesId = DB::table('mst_fieldicons')->where('key', 'intestines')->value('id');
        $stomachId = DB::table('mst_fieldicons')->where('key', 'stomach')->value('id');

        if ($intestinesId) {
            $ids = DB::table('mst_fieldicons')->whereIn('key', ['symptoms', 'warning', 'heart'])->pluck('id', 'key');
            $target = ['blood_stool' => $ids['warning'] ?? $ids['symptoms'] ?? null, 'bowel_satisfaction' => $ids['heart'] ?? null];

            foreach (self::TABLES as $table) {
                foreach ($target as $name => $iconId) {
                    DB::table($table)->where('fieldIconId', $intestinesId)->where('name', $name)->update(['fieldIconId' => $iconId]);
                }
                DB::table($table)->where('fieldIconId', $intestinesId)->update(['fieldIconId' => $stomachId]);
            }
            DB::table('mst_fieldicons')->where('id', $intestinesId)->delete();
        }

        foreach (self::RENAMES as $oldKey => [$key, $label, $color]) {
            DB::table('mst_fieldicons')->where('key', $oldKey)->update(['key' => $key, 'label' => $label, 'colorToken' => $color]);
        }
        DB::table('mst_fieldicons')->where('key', 'heart')->update(['label' => 'Cor']);
    }

    public function down(): void
    {
        foreach (self::RENAMES as $oldKey => [$key]) {
            DB::table('mst_fieldicons')->where('key', $key)->update(['key' => $oldKey]);
        }
        $labels = ['leaf' => ['Fulla', 'emerald'], 'utensils' => ['Coberts', 'emerald'], 'warning' => ['Advertència', 'amber'], 'document' => ['Document', 'violet'], 'stomach' => ['Estómac', 'sky'], 'apple' => ['Poma', 'emerald'], 'running' => ['Persona corrent', 'sky']];
        foreach ($labels as $key => [$label, $color]) {
            DB::table('mst_fieldicons')->where('key', $key)->update(['label' => $label, 'colorToken' => $color]);
        }
    }
};
