<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    // Icones addicionals per a rutines (el mateix catàleg que ja usen els camps).
    public function up(): void
    {
        $now = now();
        DB::table('mst_fieldicons')->insert([
            ['id' => (string) Str::uuid(), 'key' => 'leaf', 'label' => 'Fulla', 'colorToken' => 'emerald', 'createdAt' => $now],
            ['id' => (string) Str::uuid(), 'key' => 'heart', 'label' => 'Cor', 'colorToken' => 'rose', 'createdAt' => $now],
            ['id' => (string) Str::uuid(), 'key' => 'apple', 'label' => 'Poma', 'colorToken' => 'emerald', 'createdAt' => $now],
            ['id' => (string) Str::uuid(), 'key' => 'running', 'label' => 'Persona corrent', 'colorToken' => 'sky', 'createdAt' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('mst_fieldicons')->whereIn('key', ['leaf', 'heart', 'apple', 'running'])->delete();
    }
};
