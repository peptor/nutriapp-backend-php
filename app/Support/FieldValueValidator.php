<?php

namespace App\Support;

// Validació autoritativa dels valors d'un registre diari, segons el tipus del camp
// de la plantilla. Mirall del validador del backend Node (lib/fieldValues.ts) i del
// frontend (frontend/src/lib/fieldValues.ts).
class FieldValueValidator
{
    private static function isValidNonNegativeNumber(mixed $value): bool
    {
        if (is_bool($value)) {
            return false;
        }
        if (! is_numeric($value)) {
            return false;
        }

        return (float) $value >= 0;
    }

    public static function validate(array $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null; // l'obligatorietat es comprova a part
        }

        $label = $field['label'];

        if ($field['fieldType'] === 'NUMBER') {
            return self::isValidNonNegativeNumber($value) ? null : "\"$label\" ha de ser un número positiu (pot tenir decimals)";
        }

        if ($field['fieldType'] === 'SCALE') {
            return (self::isValidNonNegativeNumber($value) && (float) $value <= 10)
                ? null
                : "\"$label\" ha d'estar entre 0 i 10";
        }

        if ($field['fieldType'] === 'BLOOD_PRESSURE') {
            if (! is_array($value) || array_is_list($value)) {
                return "\"$label\" necessita la tensió sistòlica i la diastòlica en mmHg";
            }
            $systolic = $value['tensio_sistolica'] ?? null;
            $diastolic = $value['tensio_diastolica'] ?? null;
            if (! self::isValidNonNegativeNumber($systolic) || ! self::isValidNonNegativeNumber($diastolic)) {
                return "\"$label\" necessita la tensió sistòlica i la diastòlica en mmHg";
            }

            return null;
        }

        return null;
    }
}
