<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\ScheduleSlot;
use Illuminate\Support\Carbon;

// Disponibilitat d'un nutricionista per a una visita sol·licitada pel pacient: es rebutja sola (amb el missatge de
// resposta) si la franja ja està ocupada per una altra visita confirmada o si el nutricionista no hi treballa
// (dia sense horari, festiu, absència, vacances o fora del seu horari d'aquell dia).
// Les hores són HORA DE RELLOTGE (vegeu AppointmentMessenger). Si el nutricionista encara no ha configurat cap horari
// de treball, l'horari NO es té en compte (només l'ocupació i les excepcions de festius/absències/vacances).
class AppointmentAvailability
{
    public static function isAvailable(string $nutricionistaId, Carbon $start, Carbon $end): bool
    {
        $busy = Appointment::confirmed()
            ->where('nutricionistaId', $nutricionistaId)
            ->where('startAt', '<', $end)
            ->where('endAt', '>', $start)
            ->exists();
        if ($busy) {
            return false;
        }

        return self::isWorking($nutricionistaId, $start, $end);
    }

    private static function isWorking(string $nutricionistaId, Carbon $start, Carbon $end): bool
    {
        $day = ScheduleResolver::resolveRange($nutricionistaId, $start->format('Y-m-d'), $start->format('Y-m-d'))[0];

        if ($day['type'] === 'NORMAL' && ! ScheduleSlot::where('nutricionistaId', $nutricionistaId)->exists()) {
            return true; // sense horari configurat: no hi ha res a comprovar
        }
        if (! $day['working']) {
            return false; // festiu, absència, vacances o dia sense horari
        }

        // Tota la visita ha de caure dins d'un mateix tram horari (o de l'horari especial del dia).
        $from = $start->format('H:i');
        $to = $end->format('H:i');
        foreach ($day['ranges'] as $range) {
            if ($from >= $range['start'] && $to <= $range['end']) {
                return true;
            }
        }

        return false;
    }
}
