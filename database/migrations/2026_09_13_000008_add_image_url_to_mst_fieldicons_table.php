<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mst_fieldicons', function (Blueprint $table) {
            // Icones il·lustrades (com les de les rutines de la biblioteca): en comptes
            // d'una icona SVG + color, es serveix directament una imatge ja acabada.
            $table->string('imageUrl')->nullable()->after('colorToken');
        });
    }

    public function down(): void
    {
        Schema::table('mst_fieldicons', function (Blueprint $table) {
            $table->dropColumn('imageUrl');
        });
    }
};
