<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Notificacions push (Web Push, PWA): l'usuari autoritza les notificacions push (`notifyByPush`, com ja fa amb el
// correu amb `notifyMessagesByEmail`) i cada dispositiu que es registra queda a `sys_push_subscriptions`.
// Vegeu docs/com-funcionen-les-alertes.md (notificacions).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_users', function (Blueprint $table) {
            $table->boolean('notifyByPush')->default(false);
        });

        Schema::create('sys_push_subscriptions', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('userId', 36);
            $table->text('endpoint');
            $table->char('endpointHash', 64)->unique(); // sha256 de l'endpoint: clau única d'un dispositiu
            $table->string('publicKey', 255); // p256dh
            $table->string('authToken', 255);
            $table->string('contentEncoding', 20)->default('aes128gcm');
            $table->string('deviceLabel', 120)->nullable(); // p. ex. "Chrome · Windows"
            $table->string('userAgent', 255)->nullable();
            $table->timestamp('lastUsedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();

            $table->foreign('userId')->references('id')->on('sys_users')->cascadeOnDelete();
            $table->index('userId');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_push_subscriptions');
        Schema::table('sys_users', function (Blueprint $table) {
            $table->dropColumn('notifyByPush');
        });
    }
};
