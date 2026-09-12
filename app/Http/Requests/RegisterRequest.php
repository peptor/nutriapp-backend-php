<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'role' => ['sometimes', Rule::in(['ADMIN', 'NUTRICIONISTA', 'PACIENT'])],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
