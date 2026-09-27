<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\RoutineField;
use Illuminate\Support\Carbon;

// Redacció d'una alerta segons qui la mira (docs/com-funcionen-les-alertes.md, secció 10).
// - Nutricionista: tècnic i breu ("Possible incidència" · "Símptomes digestius: 8/10").
// - Pacient: to tranquil, sense jerga i amb consell ("Hem detectat un valor elevat" · "Avui has registrat 8/10 en
//   símptomes digestius." · "Si aquest valor es manté o empitjora, comenta-ho amb el teu nutricionista.").
// PRIMERA VERSIÓ amb plantilles genèriques per tipus i direcció: els textos que veu el pacient han de ser validats
// per un nutricionista (docs/pendent-validacio-nutricionista.xlsx).
//
// $language: idioma de qui llegeix (App\Support\Translator). L'etiqueta del camp i el valor venen de la BD i
// només existeixen en català: surten sense traduir dins la frase, encara que la resta sí que ho estigui.
class AlertTexts
{
    /**
     * @return array{title: string, body: string, advice: ?string, label: string, value: string}
     */
    public static function for(Alert $alert, ?RoutineField $field, string $role, ?string $language = null): array
    {
        // Sense el rang entre parèntesis del final ("Cremor o acidesa (0-10)"): el valor ja va amb l'escala (8/10).
        $label = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $field?->label ?? self::labelFromMessage($alert->message)));
        $value = self::shownValue($alert, $field);
        $urgent = $alert->level === 'URGENT';

        if ($role !== 'PACIENT') {
            return [
                'title' => Translator::t($urgent ? 'alert_title_urgent' : 'alert_title_review', $language),
                'body' => "{$label}: {$value}",
                'advice' => null,
                'label' => $label,
                'value' => $value,
            ];
        }

        $when = $alert->recordDate->isSameDay(Carbon::today())
            ? Translator::t('alert_today', $language)
            : Translator::t('alert_on_date', $language, ['date' => $alert->recordDate->format('d/m/Y')]);
        $lower = self::lowerFirst($label);

        if ($alert->type === 'ALARM') {
            $title = Translator::t('alert_title_situation', $language);
            $body = $when.' '.Translator::t('alert_body_alarm', $language, ['value' => $value, 'label' => $label]);
            $advice = Translator::t('alert_advice_alarm', $language);
        } else {
            $title = Translator::t($alert->severity === 'LOW' ? 'alert_title_low' : 'alert_title_high', $language);
            $body = $when.' '.Translator::t('alert_body_range', $language, ['value' => $value, 'label' => $lower]);
            $advice = Translator::t('alert_advice_range', $language);
        }
        if ($urgent) {
            $advice .= Translator::t('alert_advice_urgent_suffix', $language);
        }

        return ['title' => $title, 'body' => $body, 'advice' => $advice, 'label' => $label, 'value' => $value];
    }

    // Valor tal com el veu una persona: 8/10 (escala), 148 / 92 mmHg (pressió), 3 kg (número amb unitat), Sí / No.
    private static function shownValue(Alert $alert, ?RoutineField $field): string
    {
        $value = (string) $alert->value;
        if ($field === null) {
            return $value;
        }
        if ($field->fieldType === 'SCALE') {
            return $value.'/'.(int) ($field->scaleMax ?? 10);
        }
        if ($field->fieldType === 'BLOOD_PRESSURE') {
            return $value.' mmHg';
        }
        if ($field->fieldType === 'NUMBER' && ! empty($field->unit)) {
            return $value.' '.$field->unit;
        }

        return $value;
    }

    // Etiqueta en minúscula dins la frase, però respectant sigles (IMC, HbA1c…): només si la segona lletra és minúscula.
    private static function lowerFirst(string $text): string
    {
        $second = mb_substr($text, 1, 1);

        return ($second !== '' && mb_strtolower($second) === $second) ? mb_strtolower(mb_substr($text, 0, 1)).mb_substr($text, 1) : $text;
    }

    // Recanvi si el camp ja no existeix a la plantilla: el missatge guardat comença per "Etiqueta: valor".
    private static function labelFromMessage(string $message): string
    {
        return trim(explode(':', $message, 2)[0]);
    }
}
