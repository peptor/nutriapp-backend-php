<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'birthDate' => ['nullable', 'date'],
            'gender' => ['sometimes', Rule::in(['NO_DEFINIT', 'HOME', 'DONA'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
