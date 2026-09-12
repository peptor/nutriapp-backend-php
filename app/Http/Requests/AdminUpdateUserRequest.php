<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateUserRequest extends FormRequest
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
            'role' => ['sometimes', Rule::in(['ADMIN', 'NUTRICIONISTA', 'PACIENT'])],
            'nutricionistaId' => ['sometimes', 'uuid'],
        ];
    }
}
