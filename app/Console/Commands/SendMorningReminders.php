<?php

namespace App\Console\Commands;

use App\Models\ReminderNotice;
use App\Support\PushSender;
use App\Support\RegisterReminders;
use App\Support\Translator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

// Recordatori del matí (docs/com-funcionen-les-alertes.md, secció 10): avisa el pacient que té rutines actives
// amb compromís diari i encara no ha registrat res avui. Programada a routes/console.php.
class SendMorningReminders extends Command
{
    protected $signature = 'reminders:morning';

    protected $description = 'Envia un avís push del matí als pacients que encara no han registrat res avui';

    public function handle(PushSender $pushSender): int
    {
        $today = Carbon::today()->toDateString();
        $assignments = RegisterReminders::pendingAssignments();
        foreach ($assignments as $assignment) {
            ReminderNotice::firstOrCreate(['assignmentId' => $assignment->id, 'period' => 'MORNING', 'recordDate' => $today]);
        }

        $patients = $assignments->pluck('patient.user')->filter()->unique('id');
        foreach ($patients as $patient) {
            $pushSender->sendToUser($patient, 'NutriEvo', Translator::t('reminder_morning', $patient->language), '/patient/today', 'daily-reminder');
        }
        $this->info("Recordatoris del matí enviats: {$patients->count()}");

        return self::SUCCESS;
    }
}
