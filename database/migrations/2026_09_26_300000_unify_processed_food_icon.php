<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // "Processats consumits" apareixia amb dues icones diferents segons la rutina: es deixa la de Símptomes i senyals.
    public function up(): void
    {
        $symptomsId = DB::table('mst_fieldicons')->where('key', 'symptoms')->value('id');
        if ($symptomsId) {
            DB::table('mst_library_routine_fields')->where('name', 'processed_food')->update(['fieldIconId' => $symptomsId]);
        }
    }

    public function down(): void
    {
        // No es desfà: era una incoherència de dades.
    }
};
