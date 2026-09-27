<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Consells: missatge que escriu el nutricionista (o ve predefinit de la biblioteca) per a una condició concreta
// d'un camp (p. ex. "Símptomes digestius ≥ 7" → "Recorda anotar què has menjat..."). Vegeu
// docs/com-funcionen-les-alertes.md. Diferent d'"Avisos" (reg_alerts): no és una incidència clínica detectada
// automàticament, és un missatge que el nutricionista tria expressament; per això no té estat ni validació quan
// l'escriu ell mateix a la seva plantilla (només els predefinits de la biblioteca, redactats per IA, en necessiten).
return new class extends Migration
{
    public function up(): void
    {
        // Predefinits de la biblioteca: es copien a la plantilla en crear-la des d'aquí (com sourceLibraryFieldId).
        Schema::create('mst_library_field_advice', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('libraryRoutineFieldId', 36);
            $table->enum('operator', ['GTE', 'GT', 'LTE', 'LT', 'EQ', 'YES', 'NO']);
            $table->decimal('thresholdValue', 10, 2)->nullable(); // null per a YES/NO (camps Sí/No)
            $table->string('message', 300);
            $table->unsignedTinyInteger('orderIndex')->default(0);
            $table->timestamp('createdAt')->useCurrent();

            $table->foreign('libraryRoutineFieldId')->references('id')->on('mst_library_routine_fields')->cascadeOnDelete();
        });

        // Els d'una plantilla concreta: els heretats de la biblioteca + els que hi afegeix el nutricionista.
        // Com la resta de camps de la plantilla, es reemplacen sencers en cada desat (RoutinesController), per
        // això `reg_advice_notices` no hi apunta amb una FK forta (vegeu més avall).
        Schema::create('mst_routine_field_advice', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('routineFieldId', 36);
            $table->enum('operator', ['GTE', 'GT', 'LTE', 'LT', 'EQ', 'YES', 'NO']);
            $table->decimal('thresholdValue', 10, 2)->nullable();
            $table->string('message', 300);
            $table->unsignedTinyInteger('orderIndex')->default(0);
            $table->string('sourceLibraryAdviceId', 36)->nullable();
            $table->timestamp('createdAt')->useCurrent();

            $table->foreign('routineFieldId')->references('id')->on('mst_routine_fields')->cascadeOnDelete();
            $table->foreign('sourceLibraryAdviceId')->references('id')->on('mst_library_field_advice')->nullOnDelete();
        });

        // Registre dels consells ja disparats (per no repetir el push el mateix dia i alimentar "Avui"). Es
        // desnormalitza fieldName/operator/thresholdValue/message perquè sobrevisqui encara que la plantilla
        // es torni a desar (els camps i els seus consells es recreen sencers a cada desat, vegeu més amunt).
        Schema::create('reg_advice_notices', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('assignmentId', 36);
            $table->string('fieldName', 60);
            $table->date('recordDate');
            $table->string('ruleKey', 40); // identifica la regla concreta (operador+llindar+resum del missatge)
            $table->string('message', 300);
            $table->timestamp('createdAt')->useCurrent();

            $table->foreign('assignmentId')->references('id')->on('reg_routine_assignments')->cascadeOnDelete();
            $table->unique(['assignmentId', 'fieldName', 'recordDate', 'ruleKey'], 'reg_advice_notices_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reg_advice_notices');
        Schema::dropIfExists('mst_routine_field_advice');
        Schema::dropIfExists('mst_library_field_advice');
    }
};
