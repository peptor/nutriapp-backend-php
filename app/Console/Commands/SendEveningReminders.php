<?php

namespace App\Console\Commands;

use App\Models\ReminderNotice;
use App\Support\PushSender;
use App\Support\RegisterReminders;
use App\Support\Translator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

// Recordatori del vespre (docs/com-funcionen-les-alertes.md, secció 10): mateix càlcul que el del matí
// (RegisterReminders::pendingAssignments), així que si el pacient ja ha registrat entre tots dos avisos, aquest
// no li arriba. Programada a routes/console.php.
class SendEveningReminders extends Command
{
    protected $signature = 'reminders:evening';

    protected $description = 'Envia un avís push del vespre als pacients que encara no han registrat res avui';

    public function handle(PushSender $pushSender): int
    {
        $today = Carbon::today()->toDateString();
        $assignments = RegisterReminders::pendingAssignments();
        foreach ($assignments as $assignment) {
            ReminderNotice::firstOrCreate(['assignmentId' => $assignment->id, 'period' => 'EVENING', 'recordDate' => $today]);
        }

        $patients = $assignments->pluck('patient.user')->filter()->unique('id');
        foreach ($patients as $patient) {
            $pushSender->sendToUser($patient, 'NutriEvo', Translator::t('reminder_evening', $patient->language), '/patient/today', 'daily-reminder');
        }
        $this->info("Recordatoris del vespre enviats: {$patients->count()}");

        return self::SUCCESS;
    }
}
