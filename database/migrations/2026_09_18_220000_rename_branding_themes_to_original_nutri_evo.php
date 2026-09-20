<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Substitueix el sistema de temes classic/v1 per original/nutri/evo: "classic" (la font
// NutriEvo Sans original) passa a dir-se "original" i en queda com a valor per defecte;
// "v1" es retira (les files que el tinguessin es reassignen a "original", perquè no quedi
// cap fila amb un valor que ja no existirà a l'enum). "nutri" (Montserrat) i "evo"
// (Poppins) són els dos temes nous.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE sys_nutricionista_profiles MODIFY COLUMN brandingTheme ENUM('classic', 'v1', 'original', 'nutri', 'evo') NOT NULL DEFAULT 'original'");
        DB::statement("UPDATE sys_nutricionista_profiles SET brandingTheme = 'original' WHERE brandingTheme IN ('classic', 'v1')");
        DB::statement("ALTER TABLE sys_nutricionista_profiles MODIFY COLUMN brandingTheme ENUM('original', 'nutri', 'evo') NOT NULL DEFAULT 'original'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE sys_nutricionista_profiles MODIFY COLUMN brandingTheme ENUM('original', 'nutri', 'evo', 'classic', 'v1') NOT NULL DEFAULT 'original'");
        DB::statement("UPDATE sys_nutricionista_profiles SET brandingTheme = 'classic' WHERE brandingTheme IN ('original', 'nutri', 'evo')");
        DB::statement("ALTER TABLE sys_nutricionista_profiles MODIFY COLUMN brandingTheme ENUM('classic', 'v1') NOT NULL DEFAULT 'classic'");
    }
};
