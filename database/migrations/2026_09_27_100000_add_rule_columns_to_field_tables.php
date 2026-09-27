<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fase 1 del disseny de regles per camp (docs/disseny-migracions-regles-camps.md): les regles d'alerta,
// tendència i adherència viuen al camp. Una columna de regla buida vol dir "usa la regla del seu tipus"
// (App\Support\FieldRules). Les plantilles copien aquestes columnes de la biblioteca i les poden editar.
return new class extends Migration
{
    private const TABLES = ['mst_routine_fields', 'mst_library_routine_fields', 'mst_field_library_items'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('alertMin', 10, 2)->nullable();
                $t->decimal('alertMax', 10, 2)->nullable();
                $t->boolean('alertValue')->nullable();
                $t->enum('alertLevel', ['NONE', 'REVIEW', 'URGENT'])->default('NONE');
                $t->unsignedTinyInteger('alertMinOccurrences')->nullable();
                $t->unsignedTinyInteger('alertWindowDays')->nullable();
                $t->unsignedTinyInteger('alertConsecutiveDays')->nullable();
                $t->decimal('trendChangeAbs', 10, 2)->nullable();
                $t->decimal('trendChangePct', 5, 2)->nullable();
                $t->unsignedTinyInteger('trendWindowDays')->default(7);
                $t->boolean('isKeyField')->default(false);
                $t->boolean('countsForAdherence')->default(true);
                $t->boolean('isSensitive')->default(false);
                $t->json('calculation')->nullable();
            });

            // goodDirection passa a admetre TARGET (mantenir-se dins l'objectiu) i serveix a tots els tipus.
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `{$table}` MODIFY `goodDirection` ENUM('LOW','HIGH','TARGET') NULL");
            }
        }

        // D'on ve un camp de plantilla: permet oferir "Restaurar valors de la biblioteca".
        Schema::table('mst_routine_fields', function (Blueprint $t) {
            $t->string('sourceLibraryFieldId', 36)->nullable();
            $t->foreign('sourceLibraryFieldId')->references('id')->on('mst_library_routine_fields')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mst_routine_fields', function (Blueprint $t) {
            $t->dropForeign(['sourceLibraryFieldId']);
            $t->dropColumn('sourceLibraryFieldId');
        });

        foreach (self::TABLES as $table) {
            DB::table($table)->where('goodDirection', 'TARGET')->update(['goodDirection' => null]);
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `{$table}` MODIFY `goodDirection` ENUM('LOW','HIGH') NULL");
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn([
                    'alertMin', 'alertMax', 'alertValue', 'alertLevel', 'alertMinOccurrences', 'alertWindowDays', 'alertConsecutiveDays',
                    'trendChangeAbs', 'trendChangePct', 'trendWindowDays', 'isKeyField', 'countsForAdherence', 'isSensitive', 'calculation',
                ]);
            });
        }
    }
};
