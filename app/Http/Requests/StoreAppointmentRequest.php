<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patientId' => ['required', 'string', 'exists:sys_patients,id'],
            'startAt' => ['required', 'date'],
            'endAt' => ['required', 'date', 'after:startAt'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
