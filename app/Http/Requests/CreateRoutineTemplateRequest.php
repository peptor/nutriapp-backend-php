<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateRoutineTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'durationDays' => ['required', 'integer', 'min:1', 'max:365'],
            'objective' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:60'],
            'tags' => ['nullable', 'string', 'max:255'],
            'isPublic' => ['sometimes', 'boolean'],
            'foodLogEnabled' => ['sometimes', 'boolean'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.name' => ['required', 'string', 'min:1', 'max:60'],
            'fields.*.label' => ['required', 'string', 'min:1', 'max:120'],
            'fields.*.fieldType' => ['required', Rule::in(['TEXT', 'NUMBER', 'SCALE', 'BOOLEAN', 'SELECT', 'MULTISELECT', 'DATETIME', 'PHOTO', 'MEAL', 'SYMPTOM', 'BLOOD_PRESSURE'])],
            'fields.*.frequency' => ['sometimes', 'string', 'max:40'],
            'fields.*.required' => ['sometimes', 'boolean'],
            'fields.*.options' => ['nullable'],
            'fields.*.orderIndex' => ['sometimes', 'integer', 'min:0'],
            'fields.*.goodDirection' => ['nullable', Rule::in(['LOW', 'HIGH'])],
            'fields.*.unit' => ['nullable', 'string', 'max:20'],
            'fields.*.helpText' => ['nullable', 'string', 'max:300'],
            'fields.*.scaleMin' => ['nullable', 'integer', 'min:0', 'max:99'],
            'fields.*.scaleMax' => ['nullable', 'integer', 'min:1', 'max:100'],
            'foods' => ['sometimes', 'array'],
            'foods.*.foodId' => ['required', 'uuid'],
            'foods.*.use' => ['required', Rule::in(['RECOMMENDED', 'LIMIT', 'AVOID'])],
            'foods.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('fields', []) as $idx => $field) {
                if (($field['fieldType'] ?? null) === 'SELECT' && (! is_array($field['options'] ?? null) || count($field['options']) === 0)) {
                    $validator->errors()->add("fields.$idx.options", 'Defineix almenys una opció');
                }
                if (($field['fieldType'] ?? null) === 'SCALE' && (int) ($field['scaleMax'] ?? 10) <= (int) ($field['scaleMin'] ?? 0)) {
                    $validator->errors()->add("fields.$idx.scaleMax", 'El màxim ha de ser més gran que el mínim');
                }
            }
        });
    }
}
