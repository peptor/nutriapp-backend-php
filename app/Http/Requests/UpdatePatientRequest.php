<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'email' => ['sometimes', 'email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'birthDate' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'gender' => ['sometimes', Rule::in(['NO_DEFINIT', 'HOME', 'DONA'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
