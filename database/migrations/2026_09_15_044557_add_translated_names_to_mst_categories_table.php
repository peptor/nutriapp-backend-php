<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mst_categories', function (Blueprint $table) {
            $table->string('nom_ca')->nullable()->after('nom_original');
            $table->string('nom_es')->nullable()->after('nom_ca');
            $table->string('nom_eu')->nullable()->after('nom_es');
            $table->string('nom_gl')->nullable()->after('nom_eu');
            $table->string('nom_pt')->nullable()->after('nom_gl');
            $table->string('nom_it')->nullable()->after('nom_pt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mst_categories', function (Blueprint $table) {
            $table->dropColumn(['nom_ca', 'nom_es', 'nom_eu', 'nom_gl', 'nom_pt', 'nom_it']);
        });
    }
};
