<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\RoutineAssignment;
use App\Support\AlertGenerator;
use App\Support\FieldFrequencies;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Alerta MISSING_DAYS (docs/com-funcionen-les-alertes.md, secció 10): si fa App\Support\AlertGenerator::MISSING_DAYS_THRESHOLD
// dies SEGUITS que una assignació amb compromís diari no té cap registre dels camps que compten, deixa de ser
// només un recordatori (push, App\Support\RegisterReminders) i passa a ser una alerta real al tauler del
// nutricionista (reg_alerts, amb estat). Es repeteix com a molt un cop (si ja n'hi ha una d'oberta, no se'n crea
// una altra); es resol sola quan torna a haver-hi un registre (App\Support\AlertGenerator::resolveMissingDays,
// cridat en desar qualsevol registre de l'assignació). Programada a routes/console.php.
class SyncMissingDaysAlerts extends Command
{
    protected $signature = 'alerts:missing-days';

    protected $description = 'Crea una alerta MISSING_DAYS per a les assignacions amb X dies seguits sense registrar';

    public function handle(): int
    {
        $threshold = AlertGenerator::MISSING_DAYS_THRESHOLD;
        $today = Carbon::today();
        $checkFrom = $today->copy()->subDays($threshold - 1);

        $assignments = RoutineAssignment::query()
            ->where('status', 'ACTIVE')
            ->whereDate('startDate', '<=', $today)
            ->whereDate('endDate', '>=', $today)
            ->with('template:id,name')
            ->get(['id', 'templateId', 'startDate']);

        $created = 0;
        foreach ($assignments as $assignment) {
            // Massa nova per poder portar ja $threshold dies sense registrar.
            if (Carbon::parse($assignment->startDate)->gt($checkFrom)) {
                continue;
            }

            $adherence = FieldFrequencies::adherenceFor($assignment->templateId);
            if ($adherence['mode'] !== 'days') {
                continue;
            }

            $hasRecentRecord = DB::table('reg_daily_records')
                ->where('assignmentId', $assignment->id)
                ->whereIn('fieldName', $adherence['counted'])
                ->whereDate('recordDate', '>=', $checkFrom->toDateString())
                ->exists();
            if ($hasRecentRecord) {
                continue;
            }

            $alreadyOpen = Alert::where('assignmentId', $assignment->id)
                ->where('type', 'MISSING_DAYS')
                ->whereIn('status', ['OPEN', 'SEEN'])
                ->exists();
            if ($alreadyOpen) {
                continue;
            }

            Alert::create([
                'assignmentId' => $assignment->id,
                'fieldName' => AlertGenerator::MISSING_DAYS_FIELD,
                'recordDate' => $today->toDateString(),
                'type' => 'MISSING_DAYS',
                'level' => 'REVIEW',
                'severity' => 'HIGH',
                'message' => mb_substr($assignment->template->name.": {$threshold} dies seguits sense registrar.", 0, 300),
            ]);
            $created++;
        }

        $this->info("Alertes de dies sense registrar creades: {$created}");

        return self::SUCCESS;
    }
}
