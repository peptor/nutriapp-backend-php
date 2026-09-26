<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un fil és una conversa entre un nutricionista i un pacient (la fila sys_patients
        // ja identifica les dues parts). Els missatges creixen amb el temps: prefix reg_.
        Schema::create('reg_message_threads', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('patientId', 36);
            $table->string('subject', 200);
            $table->timestamp('lastMessageAt')->useCurrent();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('patientId')->references('id')->on('sys_patients')->onDelete('cascade');
            $table->index(['patientId', 'lastMessageAt']);
        });

        Schema::create('reg_messages', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('threadId', 36);
            $table->string('senderId', 36);
            $table->string('recipientId', 36);
            $table->mediumText('body');
            $table->string('attachmentPath', 500)->nullable();
            $table->string('attachmentName', 255)->nullable();
            $table->string('attachmentMime', 100)->nullable();
            $table->unsignedInteger('attachmentSize')->nullable();
            $table->timestamp('readAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('threadId')->references('id')->on('reg_message_threads')->onDelete('cascade');
            $table->foreign('senderId')->references('id')->on('sys_users')->onDelete('cascade');
            $table->foreign('recipientId')->references('id')->on('sys_users')->onDelete('cascade');
            $table->index(['threadId', 'createdAt']);
            $table->index(['recipientId', 'readAt']);
        });

        // Preferència del pacient: rebre un avís (sense contingut) per correu quan té un missatge nou.
        Schema::table('sys_users', function (Blueprint $table) {
            $table->boolean('notifyMessagesByEmail')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('sys_users', function (Blueprint $table) {
            $table->dropColumn('notifyMessagesByEmail');
        });
        Schema::dropIfExists('reg_messages');
        Schema::dropIfExists('reg_message_threads');
    }
};
