<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Catàleg mestre d'icones que es poden assignar a un camp (diferents camps poden
        // compartir la mateixa icona). De moment sense pantalla d'administració: quan es
        // construeixi el selector de camps de la biblioteca, es podrà triar d'aquesta llista.
        Schema::create('mst_fieldicons', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('colorToken');
            $table->timestamp('createdAt')->useCurrent();
        });

        $now = now();
        DB::table('mst_fieldicons')->insert([
            ['id' => (string) Str::uuid(), 'key' => 'utensils', 'label' => 'Coberts', 'colorToken' => 'emerald', 'createdAt' => $now],
            ['id' => (string) Str::uuid(), 'key' => 'stomach', 'label' => 'Estómac', 'colorToken' => 'sky', 'createdAt' => $now],
            ['id' => (string) Str::uuid(), 'key' => 'warning', 'label' => 'Advertència', 'colorToken' => 'amber', 'createdAt' => $now],
            ['id' => (string) Str::uuid(), 'key' => 'document', 'label' => 'Document', 'colorToken' => 'violet', 'createdAt' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_fieldicons');
    }
};
