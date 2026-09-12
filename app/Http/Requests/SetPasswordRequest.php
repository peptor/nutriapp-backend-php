<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'newPassword' => [
                'required',
                'string',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/',
            ],
            'confirmNewPassword' => ['required', 'same:newPassword'],
        ];
    }

    public function messages(): array
    {
        return [
            'newPassword.regex' => 'La contrasenya ha de tenir mínim 8 caràcters, una lletra minúscula, una lletra majúscula i un número',
            'confirmNewPassword.same' => 'Les contrasenyes no coincideixen',
        ];
    }
}
