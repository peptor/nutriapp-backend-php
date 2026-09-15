<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Permet que un nutricionista afegeixi aliments propis (privats, només visibles per ell)
// a més de la biblioteca pública (nutricionistaId NULL). Els aliments personalitzats es
// poden versionar: si se n'edita un que ja s'ha usat en algun registre, en lloc de
// modificar-lo in situ es crea una fila nova i l'antiga queda marcada amb
// supersededByFoodId apuntant-hi. Així els registres vells (que guarden l'id de l'aliment
// tal com era llavors) continuen mostrant la versió antiga, i qualsevol selecció nova usa
// la versió vigent (supersededByFoodId NULL).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->string('nutricionistaId', 36)->nullable()->after('categoryId');
            $table->string('supersededByFoodId', 36)->nullable()->after('nutricionistaId');

            $table->foreign('nutricionistaId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('supersededByFoodId')->references('id')->on('foods')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->dropForeign(['nutricionistaId']);
            $table->dropForeign(['supersededByFoodId']);
            $table->dropColumn(['nutricionistaId', 'supersededByFoodId']);
        });
    }
};
