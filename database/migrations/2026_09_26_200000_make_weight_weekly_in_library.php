<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// El pes de "Patró saludable i control de pes" passa de diari a setmanal (una pesada per setmana és
// la pràctica habitual). Només afecta les plantilles noves creades des de la biblioteca: les existents
// conserven el seu camp diari perquè canviar-los alteraria l'adherència d'assignacions en curs.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('mst_library_routine_fields')
            ->where('name', 'weight')
            ->where('fieldType', 'NUMBER')
            ->update(['frequency' => 'weekly']);
    }

    public function down(): void
    {
        DB::table('mst_library_routine_fields')
            ->where('name', 'weight')
            ->where('fieldType', 'NUMBER')
            ->update(['frequency' => 'daily']);
    }
};
