<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Visites del pacient: el pacient pot demanar una visita (queda REQUESTED fins que el nutricionista l'accepta o la
// rebutja), veure-la amb tipus (`reason`), modalitat (presencial amb adreça o telefònica: truca el nutricionista) i
// les seves notes, i anul·lar-la. Les visites creades pel nutricionista continuen existint i queden CONFIRMED.
// Cada visita pot tenir un fil de missatges (`threadId`) i cada missatge pot enllaçar a una visita (`appointmentId`)
// perquè el nutricionista vegi els botons Acceptar/Rebutjar dins el missatge de la sol·licitud.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reg_appointments', function (Blueprint $table) {
            $table->enum('status', ['REQUESTED', 'CONFIRMED', 'CANCELLED', 'REJECTED'])->default('CONFIRMED');
            $table->enum('modality', ['IN_PERSON', 'PHONE'])->default('IN_PERSON');
            $table->string('location', 255)->nullable();
            $table->text('patientNotes')->nullable();
            $table->enum('requestedBy', ['PATIENT', 'NUTRICIONISTA'])->default('NUTRICIONISTA');
            $table->string('threadId', 36)->nullable();
            $table->timestamp('cancelledAt')->nullable();
            $table->foreign('threadId')->references('id')->on('reg_message_threads')->nullOnDelete();
            $table->index(['patientId', 'startAt']);
        });

        Schema::table('reg_messages', function (Blueprint $table) {
            $table->string('appointmentId', 36)->nullable();
            $table->foreign('appointmentId')->references('id')->on('reg_appointments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reg_messages', function (Blueprint $table) {
            $table->dropForeign(['appointmentId']);
            $table->dropColumn('appointmentId');
        });
        Schema::table('reg_appointments', function (Blueprint $table) {
            $table->dropForeign(['threadId']);
            $table->dropIndex(['patientId', 'startAt']);
            $table->dropColumn(['status', 'modality', 'location', 'patientNotes', 'requestedBy', 'threadId', 'cancelledAt']);
        });
    }
};
