<?php

namespace App\Console\Commands;

use App\Models\RoutineAssignment;
use App\Support\AlertGenerator;
use Illuminate\Console\Command;

// Recalcula les alertes (reg_alerts) de totes les assignacions: útil després d'activar regles noves o per omplir
// l'històric d'assignacions anteriors a la taula. Vegeu docs/com-funcionen-les-alertes.md.
class SyncAlerts extends Command
{
    protected $signature = 'alerts:sync';

    protected $description = 'Recalcula les alertes de totes les assignacions';

    public function handle(): int
    {
        $count = 0;
        RoutineAssignment::with(['template.fields', 'patient'])->chunk(100, function ($assignments) use (&$count) {
            foreach ($assignments as $assignment) {
                AlertGenerator::sync($assignment);
                $count++;
            }
        });
        $this->info("Assignacions processades: {$count}");

        return self::SUCCESS;
    }
}
