<?php

namespace App\Support;

use App\Models\AdviceNotice;
use App\Models\RoutineAssignment;
use App\Models\RoutineFieldAdvice;
use Illuminate\Support\Carbon;

// Consells (docs/com-funcionen-les-alertes.md): a diferència de les alertes (`AlertGenerator`), no és una
// incidència clínica detectada automàticament — és un missatge que el nutricionista tria expressament per a
// una condició d'un camp. Només s'avalua per als registres d'AVUI (és un recordatori del dia, no un
// seguiment): si el pacient edita un registre d'un dia anterior no en surt cap. Es repeteix cada dia que la
// condició torni a complir-se (no té estat obert/vist/resolt).
class AdviceGenerator
{
    public static function sync(RoutineAssignment $assignment): void
    {
        $assignment->loadMissing(['template.fields.advice', 'patient.user']);
        $today = Carbon::today()->toDateString();
        $todayRecords = $assignment->records()->whereDate('recordDate', $today)->get()->keyBy('fieldName');
        if ($todayRecords->isEmpty()) {
            return;
        }

        $newCount = 0;
        foreach ($assignment->template->fields as $field) {
            $record = $todayRecords->get($field->name);
            if (! $record || $field->advice->isEmpty()) {
                continue;
            }
            foreach ($field->advice as $advice) {
                if (! self::matches($advice, $record->value)) {
                    continue;
                }
                $notice = AdviceNotice::firstOrCreate(
                    ['assignmentId' => $assignment->id, 'fieldName' => $field->name, 'recordDate' => $today, 'ruleKey' => self::ruleKey($advice)],
                    ['message' => $advice->message]
                );
                if ($notice->wasRecentlyCreated) {
                    $newCount++;
                }
            }
        }

        // Push GENÈRIC (com la resta): el text del consell només es veu dins l'app, mai a la notificació.
        if ($newCount > 0 && $assignment->patient->user) {
            $user = $assignment->patient->user;
            app(PushSender::class)->sendToUser(
                $user,
                'NutriEvo',
                Translator::t($newCount === 1 ? 'advice_new_one' : 'advice_new_many', $user->language, ['n' => $newCount]),
                '/patient/today',
                'advice'
            );
        }
    }

    private static function matches(RoutineFieldAdvice $advice, mixed $rawValue): bool
    {
        if (in_array($advice->operator, ['YES', 'NO'], true)) {
            $isYes = in_array($rawValue, [true, 1, '1', 'true'], true);

            return $advice->operator === 'YES' ? $isYes : ! $isYes;
        }
        if (! is_numeric($rawValue) || $advice->thresholdValue === null) {
            return false;
        }
        $value = (float) $rawValue;
        $threshold = (float) $advice->thresholdValue;

        return match ($advice->operator) {
            'GTE' => $value >= $threshold,
            'GT' => $value > $threshold,
            'LTE' => $value <= $threshold,
            'LT' => $value < $threshold,
            'EQ' => abs($value - $threshold) < 0.001,
            default => false,
        };
    }

    // Identifica la regla concreta (no l'id de la fila, que es recrea cada cop que es desa la plantilla).
    private static function ruleKey(RoutineFieldAdvice $advice): string
    {
        return $advice->operator.':'.($advice->thresholdValue ?? '').':'.substr(md5($advice->message), 0, 8);
    }
}
