<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FieldLibraryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:60'],
            'label' => ['required', 'string', 'min:1', 'max:120'],
            'fieldType' => ['required', Rule::in(['TEXT', 'NUMBER', 'SCALE', 'BOOLEAN', 'SELECT', 'MULTISELECT', 'DATETIME', 'PHOTO', 'MEAL', 'SYMPTOM', 'BLOOD_PRESSURE'])],
            'frequency' => ['sometimes', 'string', 'max:30'],
            'required' => ['sometimes', 'boolean'],
            'options' => ['nullable'],
            'helpText' => ['nullable', 'string', 'max:500'],
            'scaleMin' => ['nullable', 'integer', 'min:0', 'max:99'],
            'scaleMax' => ['nullable', 'integer', 'min:1', 'max:100', 'gt:scaleMin'],
        ];
    }
}
