<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patientId' => ['sometimes', 'string', 'exists:sys_patients,id'],
            'startAt' => ['sometimes', 'date'],
            'endAt' => ['sometimes', 'date', 'after:startAt'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
