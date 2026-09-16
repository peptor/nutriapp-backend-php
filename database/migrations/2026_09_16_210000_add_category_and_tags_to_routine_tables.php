<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Categoria (p.ex. "Cardiovascular", "Digestiu"...) i etiquetes lliures (separades per
// comes) per a la nova targeta de rutina de "Les meves rutines". mst_library_routines
// també les té perquè, en convertir una rutina de biblioteca en plantilla pròpia
// (templateFromLibrary), es copiïn automàticament a la nova plantilla.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mst_routine_templates', function (Blueprint $table) {
            $table->string('category', 60)->nullable()->after('objective');
            $table->string('tags')->nullable()->after('category');
        });

        Schema::table('mst_library_routines', function (Blueprint $table) {
            $table->string('category', 60)->nullable()->after('objective');
            $table->string('tags')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('mst_routine_templates', function (Blueprint $table) {
            $table->dropColumn(['category', 'tags']);
        });

        Schema::table('mst_library_routines', function (Blueprint $table) {
            $table->dropColumn(['category', 'tags']);
        });
    }
};
