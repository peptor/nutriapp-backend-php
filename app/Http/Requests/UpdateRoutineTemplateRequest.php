<?php

namespace App\Http\Requests;

use App\Support\FieldRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateRoutineTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'durationDays' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'objective' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:60'],
            'tags' => ['nullable', 'string', 'max:255'],
            'isPublic' => ['sometimes', 'boolean'],
            'foodLogEnabled' => ['sometimes', 'boolean'],
            'fields' => ['sometimes', 'array'],
            'fields.*.name' => ['required', 'string', 'min:1', 'max:60'],
            'fields.*.label' => ['required', 'string', 'min:1', 'max:120'],
            'fields.*.fieldType' => ['required', Rule::in(['TEXT', 'NUMBER', 'SCALE', 'BOOLEAN', 'SELECT', 'MULTISELECT', 'DATETIME', 'PHOTO', 'MEAL', 'SYMPTOM', 'BLOOD_PRESSURE'])],
            'fields.*.frequency' => ['sometimes', 'string', 'max:40'],
            'fields.*.required' => ['sometimes', 'boolean'],
            'fields.*.options' => ['nullable'],
            'fields.*.orderIndex' => ['sometimes', 'integer', 'min:0'],
            ...FieldRules::requestRules('fields.*.'),
            'fields.*.unit' => ['nullable', 'string', 'max:20'],
            'fields.*.helpText' => ['nullable', 'string', 'max:300'],
            'fields.*.scaleMin' => ['nullable', 'integer', 'min:0', 'max:99'],
            'fields.*.scaleMax' => ['nullable', 'integer', 'min:1', 'max:100'],
            'fields.*.advice' => ['sometimes', 'array', 'max:10'],
            'fields.*.advice.*.operator' => ['required', Rule::in(['GTE', 'GT', 'LTE', 'LT', 'EQ', 'YES', 'NO'])],
            'fields.*.advice.*.thresholdValue' => ['nullable', 'numeric'],
            'fields.*.advice.*.message' => ['required', 'string', 'max:300'],
            'fields.*.advice.*.orderIndex' => ['sometimes', 'integer', 'min:0'],
            'fields.*.advice.*.sourceLibraryAdviceId' => ['nullable', 'string'],
            'foods' => ['sometimes', 'array'],
            'foods.*.foodId' => ['required', 'uuid'],
            'foods.*.use' => ['required', Rule::in(['RECOMMENDED', 'LIMIT', 'AVOID'])],
            'foods.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return FieldRules::requestMessages('fields.*.');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('fields', []) as $idx => $field) {
                if (($field['fieldType'] ?? null) === 'SCALE' && (int) ($field['scaleMax'] ?? 10) <= (int) ($field['scaleMin'] ?? 0)) {
                    $validator->errors()->add("fields.$idx.scaleMax", 'El màxim ha de ser més gran que el mínim');
                }
                foreach (($field['advice'] ?? []) as $aIdx => $advice) {
                    $numeric = in_array($advice['operator'] ?? null, ['GTE', 'GT', 'LTE', 'LT', 'EQ'], true);
                    if ($numeric && ($advice['thresholdValue'] ?? null) === null) {
                        $validator->errors()->add("fields.$idx.advice.$aIdx.thresholdValue", 'Cal un valor per a aquest operador');
                    }
                    if (! $numeric && ! in_array(($field['fieldType'] ?? null), ['BOOLEAN'], true)) {
                        $validator->errors()->add("fields.$idx.advice.$aIdx.operator", "«Sí»/«No» només per a camps Sí/No");
                    }
                    if ($numeric && ! in_array(($field['fieldType'] ?? null), ['NUMBER', 'SCALE'], true)) {
                        $validator->errors()->add("fields.$idx.advice.$aIdx.operator", 'Aquest operador només és per a camps numèrics o d’escala');
                    }
                }
            }
        });
    }
}
