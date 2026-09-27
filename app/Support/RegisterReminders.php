<?php

namespace App\Support;

use App\Models\RoutineAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Assignacions (i pacients) pendents de registrar avui: alimenta els recordatoris de push del matí i del vespre
// (docs/com-funcionen-les-alertes.md, secció 10) i el comptador "Recordatoris" de l'Avui del pacient.
//
// NO són `reg_alerts`: els genera l'ABSÈNCIA d'un registre, no un valor, no calen estat ni pantalla pel
// nutricionista. El mateix càlcul serveix per a les dues hores: si el pacient ja ha registrat entre el
// recordatori del matí i el del vespre, deixa de sortir aquí i no rep el segon avís (no calen marques ni flags)
// — la persistència (`reg_reminder_notices`) és només per comptar-los després, no per deduplicar l'enviament.
//
// "Compromís diari" = `FieldFrequencies::adherenceFor` en mode 'days' (hi ha camps que compten per a l'adherència
// i no són setmanals): una rutina només setmanal ('weeks') o sense cap camp que compti ('none') no té sentit que
// generi un "encara no has registrat avui".
class RegisterReminders
{
    /** @return Collection<int, RoutineAssignment> assignacions actives amb compromís diari, pendents de registrar avui. */
    public static function pendingAssignments(): Collection
    {
        $today = Carbon::today()->toDateString();

        $assignments = RoutineAssignment::query()
            ->where('status', 'ACTIVE')
            ->whereDate('startDate', '<=', $today)
            ->whereDate('endDate', '>=', $today)
            ->with(['patient:id,userId', 'patient.user'])
            ->get(['id', 'templateId', 'patientId', 'startDate', 'endDate']);

        return $assignments->filter(function (RoutineAssignment $assignment) use ($today) {
            $adherence = FieldFrequencies::adherenceFor($assignment->templateId);
            if ($adherence['mode'] !== 'days' || ! $assignment->patient) {
                return false;
            }

            return ! DB::table('reg_daily_records')
                ->where('assignmentId', $assignment->id)
                ->whereIn('fieldName', $adherence['counted'])
                ->whereDate('recordDate', $today)
                ->exists();
        })->values();
    }

    /** @return Collection<int, User> Pacients (userId únics) amb alguna rutina activa pendent de registrar avui. */
    public static function pendingPatients(): Collection
    {
        $userIds = self::pendingAssignments()->pluck('patient.userId')->unique()->filter();

        return $userIds->isEmpty() ? collect() : User::whereIn('id', $userIds)->get();
    }
}
