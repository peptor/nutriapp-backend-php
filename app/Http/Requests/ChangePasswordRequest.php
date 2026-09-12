<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string', 'min:1'],
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
            'currentPassword.required' => 'Cal indicar la contrasenya actual',
            'newPassword.regex' => 'La contrasenya ha de tenir mínim 8 caràcters, una lletra minúscula, una lletra majúscula i un número',
            'confirmNewPassword.same' => 'Les contrasenyes no coincideixen',
        ];
    }
}
