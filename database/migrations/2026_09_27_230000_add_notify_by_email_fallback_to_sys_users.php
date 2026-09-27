<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Correu de fallback quan una push no arriba enlloc (sense dispositius o amb les push desactivades): interruptor
// propi, independent de `notifyMessagesByEmail` (que és només per a missatges), disponible per a tots dos rols.
// Vegeu App\Support\PushSender i docs/com-funcionen-les-alertes.md.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_users', function (Blueprint $table) {
            $table->boolean('notifyByEmailFallback')->default(false)->after('notifyByPush');
        });
    }

    public function down(): void
    {
        Schema::table('sys_users', function (Blueprint $table) {
            $table->dropColumn('notifyByEmailFallback');
        });
    }
};
