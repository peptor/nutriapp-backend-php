<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Convenció de noms de taula acordada amb l'usuari (2026-09-16): tota taula ha de començar
// per mst_ (dades "master" estàtiques/de definició), reg_ (registres que es repeteixen,
// històrics) o sys_ (control de l'aplicació, com els usuaris). Aquesta migració renombra
// totes les taules pròpies de l'app que encara no en tenien cap; les taules internes de
// Laravel (cache, jobs, migrations, password_reset_tokens, personal_access_tokens...) es
// deixen tal qual perquè el framework en depèn pel seu propi nom via configuració.
return new class extends Migration
{
    private const RENAMES = [
        // sys_ — control de l'aplicació
        'users' => 'sys_users',
        'patients' => 'sys_patients',
        'nutricionista_profiles' => 'sys_nutricionista_profiles',

        // mst_ — dades mestres / definicions estàtiques
        'food_categories' => 'mst_food_categories',
        'foods' => 'mst_foods',
        'field_library_items' => 'mst_field_library_items',
        'library_routines' => 'mst_library_routines',
        'library_routine_fields' => 'mst_library_routine_fields',
        'library_routine_foods' => 'mst_library_routine_foods',
        'library_routine_instructions' => 'mst_library_routine_instructions',
        'routine_templates' => 'mst_routine_templates',
        'routine_fields' => 'mst_routine_fields',
        'routine_instructions' => 'mst_routine_instructions',
        'routine_template_foods' => 'mst_routine_template_foods',

        // reg_ — registres que es repeteixen / històrics
        'daily_records' => 'reg_daily_records',
        'routine_assignments' => 'reg_routine_assignments',
        'food_favorites' => 'reg_food_favorites',
        'food_recent_selections' => 'reg_food_recent_selections',
        'access_logs' => 'reg_access_logs',
        'alerts' => 'reg_alerts',
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $from => $to) {
            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                Schema::rename($from, $to);
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::RENAMES) as $from => $to) {
            if (Schema::hasTable($to) && ! Schema::hasTable($from)) {
                Schema::rename($to, $from);
            }
        }
    }
};
