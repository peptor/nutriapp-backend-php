<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdminCreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'role' => ['required', Rule::in(['ADMIN', 'NUTRICIONISTA', 'PACIENT'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'nutricionistaId' => ['nullable', 'uuid'],
            'birthDate' => ['nullable', 'string'],
            'gender' => ['sometimes', Rule::in(['NO_DEFINIT', 'HOME', 'DONA'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();
            if (($data['role'] ?? null) === 'PACIENT' && empty($data['nutricionistaId'])) {
                $validator->errors()->add('nutricionistaId', 'Un pacient ha de tenir un nutricionista assignat');
            }
            if (($data['role'] ?? null) !== 'PACIENT' && empty($data['password'])) {
                $validator->errors()->add('password', 'La contrasenya és obligatòria per a aquest rol');
            }
        });
    }
}
