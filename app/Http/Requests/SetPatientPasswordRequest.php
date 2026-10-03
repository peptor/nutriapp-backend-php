<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Contrasenya nova d'un pacient posada pel seu nutricionista: mateixa política que ChangePasswordRequest, sense la contrasenya actual.
class SetPatientPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'newPassword' => ['required', 'string', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/'],
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
