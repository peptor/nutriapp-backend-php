<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminGrantLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'endsAt' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'endsAt.required' => 'Cal indicar fins a quina data té la llicència EvoPro',
            'endsAt.date_format' => 'La data no és vàlida',
            'endsAt.after_or_equal' => 'La data de fi no pot ser anterior a avui',
        ];
    }
}
